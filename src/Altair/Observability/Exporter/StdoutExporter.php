<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Observability\Exporter;

use Altair\Observability\Contracts\ExporterInterface;
use Altair\Observability\Trace\SpanStatus;
use Override;

use const STDOUT;

/**
 * Dev exporter: prints a one-line summary per span and per metric to stdout
 * (or any writable stream passed to the constructor). Useful while writing
 * instrumentation — feedback without an OTLP collector or JSONL tail.
 *
 * @psalm-suppress UnusedClass — Used as the default dev exporter via DI.
 */
final class StdoutExporter implements ExporterInterface
{
    /**
     * @param resource $stream
     */
    public function __construct(private $stream = STDOUT) {}

    #[Override]
    public function export(array $spans, array $metrics): void
    {
        foreach ($spans as $span) {
            $marker = $span->status === SpanStatus::Error ? '✗' : '✓';
            fwrite($this->stream, \sprintf(
                "[span ] %s %s  %.1fms  trace=%s\n",
                $marker,
                $span->name,
                $span->durationMs(),
                substr($span->context->traceId, 0, 8),
            ));
        }

        foreach ($metrics as $metric) {
            fwrite($this->stream, \sprintf(
                "[%-5s] %s = %s%s\n",
                $metric->kind->value,
                $metric->name,
                $metric->value,
                $metric->unit !== null ? ' ' . $metric->unit : '',
            ));
        }
    }
}
