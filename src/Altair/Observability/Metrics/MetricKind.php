<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Observability\Metrics;

/**
 * The three OTLP metric kinds the v1 Meter ships:
 *
 *  - `counter`   — monotonically increasing total (e.g. requests_total).
 *  - `gauge`     — point-in-time observation (e.g. memory_in_use).
 *  - `histogram` — distribution of measurements (e.g. request_duration_ms).
 *
 * The string value is what the exporter emits in the OTLP-JSON payload.
 */
enum MetricKind: string
{
    case Counter = 'counter';
    case Gauge = 'gauge';
    case Histogram = 'histogram';
}
