<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Observability\Recorder;

use Altair\Observability\Contracts\ExporterInterface;
use Altair\Observability\Contracts\RecorderInterface;
use Altair\Observability\Metrics\MetricPoint;
use Altair\Observability\Trace\Span;
use Override;

/**
 * In-memory buffer of finished spans and recorded metric points, with an
 * optional batch exporter that flushes the buffer either on demand or when
 * the buffer hits a soft size limit.
 *
 * Buffers are bounded ({@see $maxBufferedSpans} / {@see $maxBufferedPoints})
 * so a long-running process or a forgotten flush cannot OOM the host — when
 * the cap is hit and an exporter is bound, the recorder auto-flushes; if no
 * exporter is bound, the oldest entries roll off.
 */
final class InMemoryRecorder implements RecorderInterface
{
    /**
     * @var list<Span>
     */
    private array $spans = [];

    /**
     * @var list<MetricPoint>
     */
    private array $points = [];

    public function __construct(
        private readonly ?ExporterInterface $exporter = null,
        private readonly int $maxBufferedSpans = 1_000,
        private readonly int $maxBufferedPoints = 5_000,
    ) {}

    #[Override]
    public function recordSpan(Span $span): void
    {
        $this->spans[] = $span;

        if (\count($this->spans) >= $this->maxBufferedSpans) {
            if ($this->exporter instanceof ExporterInterface) {
                $this->flush();
            } else {
                array_shift($this->spans);
            }
        }
    }

    #[Override]
    public function recordMetric(MetricPoint $point): void
    {
        $this->points[] = $point;

        if (\count($this->points) >= $this->maxBufferedPoints) {
            if ($this->exporter instanceof ExporterInterface) {
                $this->flush();
            } else {
                array_shift($this->points);
            }
        }
    }

    #[Override]
    public function flush(): void
    {
        if (!$this->exporter instanceof ExporterInterface) {
            return;
        }

        if ($this->spans !== [] || $this->points !== []) {
            $this->exporter->export($this->spans, $this->points);
            $this->spans = [];
            $this->points = [];
        }
    }

    /**
     * @return list<Span>
     */
    public function spans(): array
    {
        return $this->spans;
    }

    /**
     * @return list<MetricPoint>
     */
    public function metrics(): array
    {
        return $this->points;
    }
}
