<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Doctor\Process;

use Altair\Doctor\Contracts\ProcessRunnerInterface;
use Override;

/**
 * Runs commands via `proc_open()` with the argv array form — no shell, so
 * no quoting or injection surface. Captures stdout/stderr and the exit code.
 */
final readonly class ShellProcessRunner implements ProcessRunnerInterface
{
    /**
     * @param list<string> $command
     */
    #[Override]
    public function run(array $command, ?string $cwd = null): ProcessResult
    {
        $descriptors = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptors, $pipes, $cwd);
        if (!\is_resource($process)) {
            return new ProcessResult(127, '', 'Unable to start process: ' . implode(' ', $command));
        }

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        return new ProcessResult(
            $exitCode,
            \is_string($stdout) ? $stdout : '',
            \is_string($stderr) ? $stderr : '',
        );
    }
}
