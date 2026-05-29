<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Eval\Runner;

use Altair\Eval\EvalRequest;

/**
 * Translates an {@see EvalRequest}'s guard-rail toggles into the concrete
 * `php -d` ini flags and environment variables that confine the spawned
 * subprocess.
 *
 * The enforceable confinements are the ones PHP itself respects: `memory_limit`
 * (hard cap), `open_basedir` (filesystem confinement), and `disable_functions`
 * (subprocess execution and, when --no-network is in effect, the function-based
 * network calls). The wall-clock timeout is enforced by the parent
 * (SIGTERM → SIGKILL), not via `max_execution_time` (which is ignored in CLI).
 *
 * Three guards are advisory rather than ini-enforced and are surfaced as env
 * vars so a cooperating host can honour them. They are documented as such:
 *
 *   - `eval()` is a language construct, not a function — PHP cannot block it
 *     via `disable_functions`. The subprocess sandbox (memory/open_basedir)
 *     still bounds the damage.
 *   - Raw-socket and curl handles can be disabled by name when network is off,
 *     but kernel-level network blocking is out of scope.
 *   - Read-only database access is host-cooperative: the host's persistence
 *     config can read `ALTAIR_EVAL_ALLOW_WRITES` and choose to refuse writes.
 *     PHP has no generic way to enforce this from outside the host's wiring.
 *
 * `unsafe` lifts every ini-level guard simultaneously — there is then no
 * `disable_functions`, no `open_basedir`, no enforced memory cap.
 */
final readonly class SecurityProfile
{
    /**
     * Subprocess-exec primitives are always blocked unless `unsafe`. Without
     * these the snippet cannot spawn a process to escape the sandbox.
     *
     * @var list<string>
     */
    private const array DISABLED_BASE = [
        // Process spawning — block every way to start another process.
        'exec',
        'shell_exec',
        'passthru',
        'system',
        'proc_open',
        'proc_close',
        'proc_terminate',
        'proc_nice',
        'popen',
        'pclose',
        'pcntl_exec',
        'assert',
        // Filesystem-link escape — symlink()/link() are not subject to
        // open_basedir on their *target* path (only on the link path), so a
        // snippet could route the wrapper's own writes outside the basedir
        // unless these are blocked.
        'symlink',
        'link',
        // Runtime ini mutation — `ini_set('open_basedir', '')` may relax the
        // basedir cap in some PHP modes; closing it removes that escape.
        'ini_set',
        'ini_alter',
        'ini_restore',
        // Environment + side-channel exfiltration paths.
        'putenv',
        'mail',
    ];

    /**
     * Additional functions blocked when --no-network is in effect.
     *
     * @var list<string>
     */
    private const array DISABLED_NETWORK = [
        'fsockopen',
        'pfsockopen',
        'stream_socket_client',
        'curl_exec',
        'curl_multi_exec',
    ];

    public function __construct(private EvalRequest $request) {}

    /**
     * @return list<string> argv-form fragment, e.g. ['-d', 'memory_limit=128M', '-d', '...']
     */
    public function phpFlags(string $allowedBaseDir): array
    {
        if ($this->request->unsafe) {
            return [];
        }

        $flags = [
            '-d', 'memory_limit=' . $this->request->memoryLimitMb . 'M',
            '-d', 'open_basedir=' . $allowedBaseDir,
            '-d', 'disable_functions=' . implode(',', $this->disabledFunctions()),
            '-d', 'max_execution_time=0',
            '-d', 'display_errors=stderr',
            '-d', 'log_errors=0',
        ];

        if (!$this->request->allowNetwork) {
            $flags[] = '-d';
            $flags[] = 'allow_url_fopen=0';
        }

        return $flags;
    }

    /**
     * The env vars passed to the subprocess. Hosts that cooperate (e.g. a
     * persistence Configuration that reads `ALTAIR_EVAL_ALLOW_WRITES`) can
     * tighten themselves when these are set.
     *
     * @return array<string, string>
     */
    public function envVars(): array
    {
        return [
            'ALTAIR_EVAL_ALLOW_WRITES' => $this->request->allowWrites ? '1' : '0',
            'ALTAIR_EVAL_ALLOW_NETWORK' => $this->request->allowNetwork ? '1' : '0',
            'ALTAIR_EVAL_UNSAFE' => $this->request->unsafe ? '1' : '0',
        ];
    }

    /**
     * @return list<string>
     */
    private function disabledFunctions(): array
    {
        $disabled = self::DISABLED_BASE;
        if (!$this->request->allowNetwork) {
            return [...$disabled, ...self::DISABLED_NETWORK];
        }

        return $disabled;
    }
}
