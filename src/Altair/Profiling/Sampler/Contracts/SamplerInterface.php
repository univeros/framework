<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Profiling\Sampler\Contracts;

use Altair\Profiling\Model\SampleLog;

/**
 * A statistical sampling-profiler backend.
 *
 * `start()` and `stop()` bracket a profiled section; the implementation may
 * record more samples on its own schedule between them. `stop()` returns the
 * collected {@see SampleLog}.
 */
interface SamplerInterface
{
    public function start(): void;

    public function stop(): SampleLog;

    /**
     * Backend identifier used in reports — `excimer`, `xdebug`, or a custom
     * test backend. Stable across versions of the same backend.
     */
    public function backend(): string;

    public function periodUs(): int;
}
