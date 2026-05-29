<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Eval\Cli;

use Altair\Cli\Attribute\Argument;
use Altair\Cli\Attribute\Command;
use Altair\Cli\Attribute\Option;
use Altair\Eval\EvalRequest;
use Altair\Eval\EvalResult;
use Altair\Eval\Evaluator;
use Altair\Eval\Support\Json;
use Altair\Events\Actor;
use Altair\Events\Contracts\RecorderInterface;
use Altair\Events\Event;
use Altair\Events\EventKind;
use Altair\Events\EventStatus;
use Throwable;

/**
 * `bin/altair eval '<php snippet>'` — execute a PHP snippet in a sandboxed
 * subprocess against the project's container and return a structured result.
 *
 * The snippet runs under `disable_functions` (no exec/shell/proc/popen/assert,
 * plus network primitives unless `--network`), `open_basedir` confinement to
 * the project root, a memory cap, and a wall-clock timeout enforced by the
 * parent (SIGTERM then SIGKILL). `--unsafe` lifts every ini-level guard and
 * emits a `kind=eval` event into `.altair/events.jsonl` so a "we let it write"
 * decision leaves an audit trail.
 *
 * Exit code is `0` on success, `1` on snippet exception/timeout/non-zero exit,
 * `2` on usage error (no snippet provided, file unreadable).
 */
#[Command(
    name: 'eval',
    description: 'Execute a PHP snippet in a sandboxed subprocess against the project container.',
)]
final readonly class EvalCommand
{
    public function __construct(
        private ?Evaluator $evaluator = null,
        private ?RecorderInterface $recorder = null,
    ) {}

    public function __invoke(
        #[Argument(description: 'PHP snippet to execute (e.g. "return 1 + 1;"). Omit to use --file.')]
        ?string $snippet = null,
        #[Option(description: 'Read the snippet from a file instead of an argument.')]
        ?string $file = null,
        #[Option(description: 'Wall-clock timeout in milliseconds (default 5000, max 60000).', name: 'timeout-ms')]
        int $timeoutMs = EvalRequest::DEFAULT_TIMEOUT_MS,
        #[Option(description: 'Memory cap in MB (default 128, max 512).', name: 'memory-mb')]
        int $memoryMb = EvalRequest::DEFAULT_MEMORY_MB,
        #[Option(description: 'Permit DB writes (host-cooperative via ALTAIR_EVAL_ALLOW_WRITES).')]
        bool $writes = false,
        #[Option(description: 'Permit network egress (lifts the network function-block).')]
        bool $network = false,
        #[Option(description: 'DANGEROUS: lift every ini guard. Emits an event audit record.')]
        bool $unsafe = false,
        #[Option(description: 'Path to a host container bootstrap file (returns a Container).')]
        ?string $bootstrap = null,
        #[Option(description: 'Output format: human or json.')]
        string $format = 'human',
    ): int {
        $code = $this->resolveSnippet($snippet, $file);
        if ($code === null) {
            echo "Provide a snippet as the first argument or --file=<path>.\n";

            return 2;
        }

        $request = new EvalRequest(
            snippet: $code,
            projectRoot: (string) getcwd(),
            timeoutMs: $timeoutMs,
            memoryLimitMb: $memoryMb,
            allowWrites: $writes,
            allowNetwork: $network,
            unsafe: $unsafe,
            bootstrap: $bootstrap,
        );

        try {
            $result = ($this->evaluator ?? new Evaluator())->evaluate($request);
        } catch (Throwable $throwable) {
            if ($unsafe) {
                $this->recordUnsafeFailure($throwable, $code, $writes, $network);
            }

            throw $throwable;
        }

        if ($unsafe) {
            $this->recordUnsafe($result, $code, $writes, $network);
        }

        echo $format === 'json' ? Json::encode($result->toArray()) : $this->human($result);

        return $result->ok() ? 0 : 1;
    }

    private function recordUnsafeFailure(Throwable $throwable, string $snippet, bool $writes, bool $network): void
    {
        if (!$this->recorder instanceof RecorderInterface) {
            return;
        }

        try {
            $this->recorder->record(Event::create(
                actor: Actor::Cli,
                command: 'eval',
                kind: EventKind::Eval,
                status: EventStatus::Fail,
                durationMs: 0,
                error: $throwable->getMessage(),
                extra: [
                    'unsafe' => true,
                    'allow_writes' => $writes,
                    'allow_network' => $network,
                    'snippet_length' => \strlen($snippet),
                    'infrastructure_failure' => true,
                ],
            ));
        } catch (Throwable) {
            // Event recording is best-effort.
        }
    }

    private function resolveSnippet(?string $snippet, ?string $file): ?string
    {
        if (\is_string($snippet) && $snippet !== '') {
            return $snippet;
        }

        if (!\is_string($file) || $file === '' || !is_file($file)) {
            return null;
        }

        $contents = file_get_contents($file);

        return \is_string($contents) && $contents !== '' ? $contents : null;
    }

    private function recordUnsafe(EvalResult $result, string $snippet, bool $writes, bool $network): void
    {
        if (!$this->recorder instanceof RecorderInterface) {
            return;
        }

        try {
            $this->recorder->record(Event::create(
                actor: Actor::Cli,
                command: 'eval',
                kind: EventKind::Eval,
                status: $result->ok() ? EventStatus::Ok : EventStatus::Fail,
                durationMs: $result->durationMs,
                extra: [
                    'unsafe' => true,
                    'allow_writes' => $writes,
                    'allow_network' => $network,
                    'snippet_length' => \strlen($snippet),
                    'timed_out' => $result->timedOut,
                ],
            ));
        } catch (Throwable) {
            // Event recording is best-effort.
        }
    }

    private function human(EvalResult $result): string
    {
        $lines = [];
        if ($result->timedOut) {
            $lines[] = '✗ Timed out after ' . $result->durationMs . 'ms.';
        } elseif ($result->exception !== null) {
            $lines[] = '✗ ' . $result->exception['class'] . ': ' . $result->exception['message'];
            $lines[] = '  at ' . $result->exception['file'] . ':' . $result->exception['line'];
        } elseif ($result->result !== null) {
            $lines[] = '✓ ' . $this->summariseResult($result->result);
        } else {
            $lines[] = '✗ Subprocess exited ' . $result->exitCode . ' without producing a result.';
        }

        $lines[] = \sprintf(
            '  duration=%dms  memory=%d KB  exit=%d',
            $result->durationMs,
            (int) ($result->memoryPeakBytes / 1024),
            $result->exitCode,
        );

        if ($result->stdout !== '') {
            $lines[] = '— stdout —';
            $lines[] = rtrim($result->stdout);
        }

        if ($result->stderr !== '') {
            $lines[] = '— stderr —';
            $lines[] = rtrim($result->stderr);
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * @param array<string, mixed> $encoded
     */
    private function summariseResult(array $encoded): string
    {
        $type = (string) ($encoded['type'] ?? 'unknown');

        return match ($type) {
            'null' => 'null',
            'bool' => $encoded['value'] === true ? 'true' : 'false',
            'int', 'float' => $type . ' = ' . var_export($encoded['value'], true),
            'string' => 'string = ' . var_export($encoded['value'], true),
            'array' => \sprintf('array(%d)', (int) ($encoded['count'] ?? 0)),
            'object' => 'object ' . ($encoded['class'] ?? '?'),
            'iterable' => 'iterable ' . ($encoded['class'] ?? '?'),
            default => $type,
        };
    }
}
