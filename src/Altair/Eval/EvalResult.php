<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Eval;

/**
 * The outcome of one sandboxed evaluation.
 *
 * Exactly one of `result` or `exception` is non-null on a clean run; both are
 * null when the subprocess died abnormally (timeout, fatal error, OOM,
 * sandbox-blocked write of the result file). `timedOut` is true precisely when
 * the parent killed the subprocess for exceeding `timeoutMs`.
 */
final readonly class EvalResult
{
    /**
     * @param array<string, mixed>|null $result
     * @param array<string, mixed>|null $exception
     */
    public function __construct(
        public ?array $result,
        public string $stdout,
        public string $stderr,
        public ?array $exception,
        public int $durationMs,
        public int $memoryPeakBytes,
        public int $exitCode,
        public bool $timedOut,
    ) {}

    public function ok(): bool
    {
        return !$this->timedOut && $this->exception === null && $this->exitCode === 0;
    }

    /**
     * @return array{
     *     ok: bool,
     *     result: array<string, mixed>|null,
     *     stdout: string,
     *     stderr: string,
     *     exception: array<string, mixed>|null,
     *     duration_ms: int,
     *     memory_peak_bytes: int,
     *     exit_code: int,
     *     timed_out: bool
     * }
     */
    public function toArray(): array
    {
        return [
            'ok' => $this->ok(),
            'result' => $this->result,
            'stdout' => $this->stdout,
            'stderr' => $this->stderr,
            'exception' => $this->exception,
            'duration_ms' => $this->durationMs,
            'memory_peak_bytes' => $this->memoryPeakBytes,
            'exit_code' => $this->exitCode,
            'timed_out' => $this->timedOut,
        ];
    }
}
