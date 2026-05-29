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
 * A bundle of samples produced by one profiling run, with the sampler period
 * (in microseconds) so downstream code can convert sample counts into wall-time.
 */
final readonly class SampleLog
{
    /**
     * @param list<Sample> $samples
     */
    public function __construct(
        public array $samples,
        public int $periodUs,
        public string $backend,
    ) {}

    public function totalSamples(): int
    {
        $total = 0;
        foreach ($this->samples as $sample) {
            $total += $sample->count;
        }

        return $total;
    }
}
