<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Observability\Contracts;

use Altair\Observability\Metrics\MetricPoint;
use Altair\Observability\Trace\Span;

/**
 * Where finished spans and recorded metric points land.
 *
 * Production exporters (OTLP HTTP, Prometheus pull) implement this with a
 * batched flush; the in-memory implementation buffers and is read by
 * `observability:tail` / `observability:stats` / `observability:export`.
 */
interface RecorderInterface
{
    public function recordSpan(Span $span): void;

    public function recordMetric(MetricPoint $point): void;

    public function flush(): void;
}
