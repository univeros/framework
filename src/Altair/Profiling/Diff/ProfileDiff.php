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
 * The diff of two profile runs: the killer feature for refactor confidence.
 *
 * Given a baseline and a head, list every function whose self-time changed by
 * more than {@see SIGNIFICANCE_PERCENT} in either direction, sorted by absolute
 * change. A regression is a positive (slower) change above
 * {@see REGRESSION_THRESHOLD_PERCENT} on a function carrying at least
 * {@see REGRESSION_MIN_SAMPLES} samples in HEAD — that floor keeps the noisy
 * tail of one-sample functions out of the regression list.
 */
final readonly class ProfileDiff
{
    public const float SIGNIFICANCE_PERCENT = 5.0;

    public const float REGRESSION_THRESHOLD_PERCENT = 10.0;

    public const int REGRESSION_MIN_SAMPLES = 5;

    /**
     * @param list<ChangedFunction> $changes      sorted by abs(deltaPercent) descending
     * @param list<ChangedFunction> $regressions  subset of $changes
     */
    public function __construct(
        public string $baseId,
        public string $headId,
        public int $baseTotalSamples,
        public int $headTotalSamples,
        public float $deltaTotalPercent,
        public array $changes,
        public array $regressions,
    ) {}

    public function hasRegressions(): bool
    {
        return $this->regressions !== [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'base_id' => $this->baseId,
            'head_id' => $this->headId,
            'base_total_samples' => $this->baseTotalSamples,
            'head_total_samples' => $this->headTotalSamples,
            'delta_total_percent' => $this->deltaTotalPercent,
            'changes' => array_map(static fn(ChangedFunction $c): array => $c->toArray(), $this->changes),
            'regressions' => array_map(static fn(ChangedFunction $c): array => $c->toArray(), $this->regressions),
            'has_regressions' => $this->hasRegressions(),
        ];
    }
}
