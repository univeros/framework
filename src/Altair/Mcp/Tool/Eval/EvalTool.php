<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Mcp\Tool\Eval;

use Altair\Doctor\Contracts\ProcessRunnerInterface;
use Altair\Doctor\Process\ShellProcessRunner;
use Altair\Mcp\Attribute\McpTool;
use Altair\Mcp\Contracts\McpToolInterface;
use Altair\Mcp\Support\Output;
use Altair\Mcp\Support\ProjectContext;
use Override;

/**
 * Read-some/write-rare wrapper over `bin/altair eval --format=json`.
 *
 * The agent's "let me check" primitive: a snippet runs in a sandboxed
 * subprocess (disable_functions, open_basedir, memory cap, wall-clock kill)
 * against the project's container and the structured result comes back as
 * JSON. The `--unsafe` flag is deliberately NOT exposed here — lifting every
 * guard is a CLI-only, audit-logged action.
 */
#[McpTool(
    name: 'framework__eval',
    description: 'Execute a short PHP snippet in a sandboxed subprocess against the project container and return a structured result. '
        . 'Use container(FQCN::class) to resolve services. The snippet is bounded by disable_functions, open_basedir, a memory cap, and a wall-clock timeout.',
    inputSchema: __DIR__ . '/../../Schema/eval-input.json',
    outputSchema: __DIR__ . '/../../Schema/object-output.json',
)]
final readonly class EvalTool implements McpToolInterface
{
    public function __construct(
        private ProjectContext $context,
        private ProcessRunnerInterface $runner = new ShellProcessRunner(),
    ) {}

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function call(array $input): array
    {
        $snippet = \is_string($input['snippet'] ?? null) ? $input['snippet'] : '';

        // Build option flags first; the `--` end-of-options marker forces every
        // following token to be treated as a positional argument by Symfony
        // Console — so a hostile snippet starting with "--unsafe" cannot
        // promote itself to a flag and lift every guard.
        $command = ['php', 'bin/altair', 'eval', '--format=json'];

        if (\is_int($input['timeout_ms'] ?? null)) {
            $command[] = '--timeout-ms=' . $input['timeout_ms'];
        }

        if (($input['allow_writes'] ?? false) === true) {
            $command[] = '--writes';
        }

        if (($input['allow_network'] ?? false) === true) {
            $command[] = '--network';
        }

        $command[] = '--';
        $command[] = $snippet;

        $result = $this->runner->run($command, $this->context->projectRoot);

        $decoded = json_decode($result->stdout, true);
        if (\is_array($decoded)) {
            return $decoded;
        }

        return [
            'ok' => false,
            'exit_code' => $result->exitCode,
            'error' => Output::tail($result->stdout . $result->stderr),
        ];
    }
}
