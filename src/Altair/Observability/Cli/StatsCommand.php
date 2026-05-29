<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Observability\Cli;

use Altair\Cli\Attribute\Command;
use Altair\Cli\Attribute\Option;
use Altair\Observability\Support\Json;
use Altair\Observability\Support\Workspace;
use Altair\Observability\Trace\SpanStatus;

/**
 * `bin/altair observability:stats` — summary statistics across the recent
 * observability log: span count by status, request count, p50/p95/p99
 * duration, top error names, metric counters by name. Streaming-friendly:
 * holds only running counters and a duration sample slice in memory.
 */
#[Command(
    name: 'observability:stats',
    description: 'Summary stats across recent spans + metric points (counts, p50/p95/p99 durations, error counts).',
)]
final readonly class StatsCommand
{
    use Workspace;

    public function __invoke(
        #[Option(description: 'How many rows back to read (default 5000).')]
        int $limit = 5_000,
        #[Option(description: 'Output format: human or json.')]
        string $format = 'human',
    ): int {
        $spanCount = 0;
        $errorCount = 0;
        $errorNames = [];
        $durations = [];
        $counters = [];

        foreach ($this->reader()->rows($limit) as $row) {
            $kind = $row['_kind'] ?? null;
            if ($kind === 'span') {
                ++$spanCount;
                $durations[] = (float) ($row['duration_ms'] ?? 0);
                if ((int) ($row['status']['code'] ?? 0) === SpanStatus::Error->value) {
                    ++$errorCount;
                    $name = (string) ($row['name'] ?? '?');
                    $errorNames[$name] = ($errorNames[$name] ?? 0) + 1;
                }
            } elseif ($kind === 'metric' && (string) ($row['kind'] ?? '') === 'counter') {
                $name = (string) ($row['name'] ?? '?');
                $counters[$name] = ($counters[$name] ?? 0) + (float) ($row['value'] ?? 0);
            }
        }

        arsort($errorNames);
        arsort($counters);

        $stats = [
            'spans' => $spanCount,
            'errors' => $errorCount,
            'error_rate' => $spanCount === 0 ? 0.0 : round($errorCount / $spanCount * 100, 2),
            'duration_ms' => $this->percentiles($durations),
            'top_errors' => \array_slice($errorNames, 0, 5, preserve_keys: true),
            'counters' => \array_slice($counters, 0, 10, preserve_keys: true),
        ];

        if ($format === 'json') {
            echo Json::encode($stats);

            return 0;
        }

        echo $this->human($stats);

        return 0;
    }

    /**
     * @param list<float> $durations
     *
     * @return array{p50: float, p95: float, p99: float, max: float}
     */
    private function percentiles(array $durations): array
    {
        if ($durations === []) {
            return ['p50' => 0.0, 'p95' => 0.0, 'p99' => 0.0, 'max' => 0.0];
        }

        sort($durations);
        $n = \count($durations);

        return [
            'p50' => round($durations[(int) ($n * 0.50)] ?? 0.0, 2),
            'p95' => round($durations[(int) min($n - 1, $n * 0.95)] ?? 0.0, 2),
            'p99' => round($durations[(int) min($n - 1, $n * 0.99)] ?? 0.0, 2),
            'max' => round($durations[$n - 1], 2),
        ];
    }

    /**
     * @param array<string, mixed> $stats
     */
    private function human(array $stats): string
    {
        $lines = [
            \sprintf(
                'Spans: %d (errors: %d, %.2f%%)',
                (int) $stats['spans'],
                (int) $stats['errors'],
                (float) $stats['error_rate'],
            ),
            \sprintf(
                'Durations (ms): p50=%.1f  p95=%.1f  p99=%.1f  max=%.1f',
                $stats['duration_ms']['p50'],
                $stats['duration_ms']['p95'],
                $stats['duration_ms']['p99'],
                $stats['duration_ms']['max'],
            ),
        ];

        if ($stats['top_errors'] !== []) {
            $lines[] = '';
            $lines[] = 'Top errors:';
            foreach ($stats['top_errors'] as $name => $count) {
                $lines[] = \sprintf('  %-30s %d', $name, $count);
            }
        }

        if ($stats['counters'] !== []) {
            $lines[] = '';
            $lines[] = 'Counters:';
            foreach ($stats['counters'] as $name => $sum) {
                $lines[] = \sprintf('  %-30s %s', $name, $sum);
            }
        }

        return implode("\n", $lines) . "\n";
    }
}
