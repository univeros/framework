<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Observability\Cli;

use Altair\Cli\Attribute\Argument;
use Altair\Cli\Attribute\Command;
use Altair\Cli\Attribute\Option;
use Altair\Observability\Exporter\OtlpJsonExporter;
use Altair\Observability\Metrics\MetricPoint;
use Altair\Observability\Support\Json;
use Altair\Observability\Support\Workspace;
use Altair\Observability\Trace\Span;

/**
 * `bin/altair observability:export <otlp-endpoint>` — one-shot export of the
 * recent JSONL log to an OpenTelemetry Collector via OTLP/HTTP. Sends in
 * batches of {@see BATCH_SIZE} so a large log doesn't post a 100MB payload.
 *
 * The JSONL is not consumed — `export` is read-only. Run it on a schedule
 * (cron / launchd / supervisor) for periodic forwarding, or invoke it on
 * demand for one-shot ship-then-inspect.
 */
#[Command(
    name: 'observability:export',
    description: 'Export the recent JSONL observability log to an OTLP/HTTP collector endpoint.',
)]
final readonly class ExportCommand
{
    use Workspace;

    public const int BATCH_SIZE = 500;

    public function __invoke(
        #[Argument(description: 'OTLP/HTTP endpoint base URL, e.g. http://collector:4318')]
        string $endpoint,
        #[Option(description: 'Maximum rows to read from the log (default 10000).')]
        int $limit = 10_000,
        #[Option(description: 'service.name attribute stamped on each batch.', name: 'service-name')]
        string $serviceName = 'altair',
        #[Option(description: 'Output format: human or json.')]
        string $format = 'human',
    ): int {
        $exporter = new OtlpJsonExporter($endpoint, ['service.name' => $serviceName]);

        $spansBatch = [];
        $metricsBatch = [];
        $stats = ['spans' => 0, 'metrics' => 0, 'batches' => 0];

        foreach ($this->reader()->rows($limit) as $row) {
            $kind = $row['_kind'] ?? null;
            if ($kind === 'span') {
                $spansBatch[] = Span::fromArray($row);
                ++$stats['spans'];
            } elseif ($kind === 'metric') {
                $metricsBatch[] = MetricPoint::fromArray($row);
                ++$stats['metrics'];
            }

            if (\count($spansBatch) + \count($metricsBatch) >= self::BATCH_SIZE) {
                $exporter->export($spansBatch, $metricsBatch);
                $spansBatch = [];
                $metricsBatch = [];
                ++$stats['batches'];
            }
        }

        if ($spansBatch !== [] || $metricsBatch !== []) {
            $exporter->export($spansBatch, $metricsBatch);
            ++$stats['batches'];
        }

        $stats['endpoint'] = $endpoint;
        $stats['service_name'] = $serviceName;

        if ($format === 'json') {
            echo Json::encode($stats);

            return 0;
        }

        echo \sprintf(
            "Exported %d spans + %d metrics to %s in %d batch(es).\n",
            $stats['spans'],
            $stats['metrics'],
            $endpoint,
            $stats['batches'],
        );

        return 0;
    }
}
