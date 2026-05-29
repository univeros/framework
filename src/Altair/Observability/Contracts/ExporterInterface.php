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
 * Sink for a batch of spans + metric points. Implementations include the
 * JSONL append-only log, OTLP-JSON over HTTP, and a stdout pretty-printer
 * for dev.
 */
interface ExporterInterface
{
    /**
     * @param list<Span>        $spans
     * @param list<MetricPoint> $metrics
     */
    public function export(array $spans, array $metrics): void;
}
