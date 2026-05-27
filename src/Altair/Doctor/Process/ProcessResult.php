<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Doctor\Process;

/**
 * The outcome of a sub-process: exit code plus captured streams.
 */
final readonly class ProcessResult
{
    public function __construct(
        public int $exitCode,
        public string $stdout = '',
        public string $stderr = '',
    ) {}

    public function ok(): bool
    {
        return $this->exitCode === 0;
    }
}
