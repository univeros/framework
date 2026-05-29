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
 * One row of the hotspot table — the top-N functions by self-time. `percent`
 * is self-samples as a fraction of total samples in the run, so two profiles
 * are comparable even when their absolute sample counts differ.
 */
final readonly class Hotspot
{
    public function __construct(
        public string $function,
        public int $selfSamples,
        public int $totalSamples,
        public float $percent,
    ) {}

    /**
     * @return array{function: string, self_samples: int, total_samples: int, percent: float}
     */
    public function toArray(): array
    {
        return [
            'function' => $this->function,
            'self_samples' => $this->selfSamples,
            'total_samples' => $this->totalSamples,
            'percent' => $this->percent,
        ];
    }
}
