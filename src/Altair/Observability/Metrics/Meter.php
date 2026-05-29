<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Observability\Metrics;

use Altair\Observability\Contracts\RecorderInterface;

/**
 * The metrics surface: counters, gauges, and histograms.
 *
 * Each call records a single {@see MetricPoint} through the bound
 * {@see RecorderInterface}; aggregation (sum / last-value / histogram
 * bucketing) happens at export time. That keeps Meter itself stateless and
 * concurrency-friendly — no shared in-memory bucket state to lose on a fatal.
 */
final readonly class Meter
{
    public function __construct(private RecorderInterface $recorder) {}

    /**
     * Increment a counter (monotonically increasing total).
     *
     * @param array<string, scalar|null|list<scalar|null>> $attributes
     */
    public function counter(string $name, float $value = 1.0, array $attributes = [], ?string $unit = null, ?string $description = null): void
    {
        $this->recorder->recordMetric(new MetricPoint(
            $name,
            MetricKind::Counter,
            $value,
            $this->nowNs(),
            $attributes,
            $unit,
            $description,
        ));
    }

    /**
     * Record a point-in-time observation (last value wins on aggregation).
     *
     * @param array<string, scalar|null|list<scalar|null>> $attributes
     */
    public function gauge(string $name, float $value, array $attributes = [], ?string $unit = null, ?string $description = null): void
    {
        $this->recorder->recordMetric(new MetricPoint(
            $name,
            MetricKind::Gauge,
            $value,
            $this->nowNs(),
            $attributes,
            $unit,
            $description,
        ));
    }

    /**
     * Record one measurement in a distribution (collapsed into buckets at export).
     *
     * @param array<string, scalar|null|list<scalar|null>> $attributes
     */
    public function histogram(string $name, float $value, array $attributes = [], ?string $unit = null, ?string $description = null): void
    {
        $this->recorder->recordMetric(new MetricPoint(
            $name,
            MetricKind::Histogram,
            $value,
            $this->nowNs(),
            $attributes,
            $unit,
            $description,
        ));
    }

    private function nowNs(): int
    {
        return (int) (microtime(true) * 1_000_000_000);
    }
}
