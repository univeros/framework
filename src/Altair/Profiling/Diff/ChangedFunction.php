<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Profiling\Diff;

/**
 * One row of a profile diff: a function whose self-samples shifted between two
 * runs. `deltaPercent` is positive when HEAD is slower than BASE.
 */
final readonly class ChangedFunction
{
    public function __construct(
        public string $function,
        public int $baseSelfSamples,
        public int $headSelfSamples,
        public float $deltaPercent,
    ) {}

    /**
     * @return array{function: string, base_self_samples: int, head_self_samples: int, delta_percent: float}
     */
    public function toArray(): array
    {
        return [
            'function' => $this->function,
            'base_self_samples' => $this->baseSelfSamples,
            'head_self_samples' => $this->headSelfSamples,
            'delta_percent' => $this->deltaPercent,
        ];
    }
}
