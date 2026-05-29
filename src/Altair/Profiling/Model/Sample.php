<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Profiling\Model;

/**
 * One sampling-profiler observation: the call stack at the moment the sampler
 * fired, root-first (outermost frame at index 0, the leaf at the end).
 *
 * `count` is the weight — most backends emit one row per sample, but a
 * deduplicating backend may already aggregate identical stacks.
 */
final readonly class Sample
{
    /**
     * @param list<string> $stack root-first list of frames, each "Class::method" or "function"
     */
    public function __construct(
        public array $stack,
        public int $count = 1,
    ) {}
}
