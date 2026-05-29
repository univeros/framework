<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Eval\Runner;

/**
 * Runs a single subprocess under a wall-clock budget, capturing stdout and
 * stderr separately and signalling timeout (SIGTERM with a short grace,
 * SIGKILL on the way out) so a runaway snippet cannot hang the parent. Both
 * pipes are read non-blocking so a chatty subprocess cannot wedge the loop.
 *
 * Each stream is capped at {@see OUTPUT_LIMIT} bytes (head-kept with a
 * truncation marker) so a `for(;;) echo 'x';` does not balloon parent memory.
 */
final readonly class SubprocessRunner
{
    public const int OUTPUT_LIMIT = 16_384;

    private const int GRACE_AFTER_TERM_US = 100_000;

    private const int CHUNK_SIZE = 8_192;

    /**
     * @param list<string>          $command argv form (no shell)
     * @param array<string, string> $env
     */
    public function run(array $command, ?string $cwd, array $env, int $timeoutMs): SubprocessResult
    {
        $descriptors = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $start = microtime(true);
        $process = proc_open($command, $descriptors, $pipes, $cwd, $env === [] ? null : $env);
        if (!\is_resource($process)) {
            return new SubprocessResult(127, '', 'Unable to start ' . implode(' ', $command), false, 0);
        }

        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $deadline = $start + $timeoutMs / 1_000;
        $stdout = '';
        $stderr = '';
        $timedOut = false;

        while (true) {
            $status = proc_get_status($process);

            $this->drain($pipes, $stdout, $stderr);

            if (!$status['running']) {
                break;
            }

            if (microtime(true) >= $deadline) {
                $timedOut = true;
                $this->kill($process);

                break;
            }

            $remaining = max(0.0, $deadline - microtime(true));
            $this->waitForReadable($pipes, $remaining);
        }

        $this->drain($pipes, $stdout, $stderr);
        foreach ($pipes as $pipe) {
            if (\is_resource($pipe)) {
                fclose($pipe);
            }
        }

        $exitCode = proc_close($process);
        if ($timedOut) {
            $exitCode = 124;
        }

        return new SubprocessResult(
            $exitCode,
            $this->cap($stdout),
            $this->cap($stderr),
            $timedOut,
            (int) ((microtime(true) - $start) * 1_000),
        );
    }

    /**
     * Drain whatever is currently buffered on stdout/stderr.
     *
     * The pipe is always read to completion (or until EAGAIN), even after the
     * cap is reached, so a chatty subprocess does not block on a full pipe
     * buffer. Discarded-past-cap bytes never enter parent memory — this is
     * the bound that keeps a `while (true) echo …;` snippet from ballooning
     * the parent.
     *
     * @param array<int, resource> $pipes
     */
    private function drain(array $pipes, string &$stdout, string &$stderr): void
    {
        foreach ([1, 2] as $fd) {
            if (!\is_resource($pipes[$fd] ?? null)) {
                continue;
            }

            while (($chunk = fread($pipes[$fd], self::CHUNK_SIZE)) !== false && $chunk !== '') {
                if ($fd === 1) {
                    if (\strlen($stdout) < self::OUTPUT_LIMIT) {
                        $stdout .= $chunk;
                    }
                } elseif (\strlen($stderr) < self::OUTPUT_LIMIT) {
                    $stderr .= $chunk;
                }
            }
        }
    }

    /**
     * @param array<int, resource> $pipes
     */
    private function waitForReadable(array $pipes, float $seconds): void
    {
        $read = array_values(array_filter([$pipes[1] ?? null, $pipes[2] ?? null], \is_resource(...)));
        if ($read === []) {
            // Pipes already closed; spin briefly and let the status check exit the loop.
            usleep(10_000);

            return;
        }

        $write = null;
        $except = null;
        $sec = (int) $seconds;
        $usec = (int) (($seconds - $sec) * 1_000_000);

        @stream_select($read, $write, $except, $sec, $usec);
    }

    /**
     * @param resource $process
     */
    private function kill($process): void
    {
        @proc_terminate($process, 15); // SIGTERM
        usleep(self::GRACE_AFTER_TERM_US);

        $status = proc_get_status($process);
        if ($status['running']) {
            @proc_terminate($process, 9); // SIGKILL
            usleep(self::GRACE_AFTER_TERM_US);
        }
    }

    private function cap(string $output): string
    {
        if (\strlen($output) <= self::OUTPUT_LIMIT) {
            return $output;
        }

        return substr($output, 0, self::OUTPUT_LIMIT) . "\n...(truncated)";
    }
}
