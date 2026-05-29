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
 * Raw outcome of one subprocess invocation: exit code, captured streams, and
 * whether the parent killed the process for exceeding its wall-clock budget.
 */
final readonly class SubprocessResult
{
    public function __construct(
        public int $exitCode,
        public string $stdout,
        public string $stderr,
        public bool $timedOut,
        public int $durationMs,
    ) {}
}
