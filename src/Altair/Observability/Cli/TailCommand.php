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

/**
 * `bin/altair observability:tail` — tail the most recent spans and metrics
 * from `.altair/observability/`. Newest first; default 50 rows. JSON-friendly
 * for agents (`--format=json`), human-readable by default.
 */
#[Command(
    name: 'observability:tail',
    description: 'Tail the most recent spans and metric points from the observability log.',
)]
final readonly class TailCommand
{
    use Workspace;

    public function __invoke(
        #[Option(description: 'Maximum number of rows to show (default 50).')]
        int $limit = 50,
        #[Option(description: 'Restrict to one kind: span or metric.')]
        ?string $kind = null,
        #[Option(description: 'Output format: human or json.')]
        string $format = 'human',
    ): int {
        $rows = [];
        foreach ($this->reader()->rows($limit * 4) as $row) {
            if ($kind !== null && ($row['_kind'] ?? null) !== $kind) {
                continue;
            }

            $rows[] = $row;
            if (\count($rows) >= $limit) {
                break;
            }
        }

        if ($format === 'json') {
            echo Json::encode(['count' => \count($rows), 'rows' => $rows]);

            return 0;
        }

        if ($rows === []) {
            echo "No observability records found. Capture some by sending traffic through the ObservabilityMiddleware.\n";

            return 0;
        }

        foreach ($rows as $row) {
            echo $this->humanLine($row), "\n";
        }

        return 0;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function humanLine(array $row): string
    {
        if (($row['_kind'] ?? null) === 'span') {
            $status = (int) ($row['status']['code'] ?? 0) === 2 ? '✗' : '✓';

            return \sprintf(
                '[span ] %s %-22s %6.1fms  trace=%s',
                $status,
                (string) ($row['name'] ?? '?'),
                (float) ($row['duration_ms'] ?? 0),
                substr((string) ($row['trace_id'] ?? ''), 0, 8),
            );
        }

        return \sprintf(
            '[%-5s] %-22s = %s%s',
            (string) ($row['kind'] ?? ''),
            (string) ($row['name'] ?? '?'),
            (string) ($row['value'] ?? ''),
            isset($row['unit']) && \is_string($row['unit']) && $row['unit'] !== '' ? ' ' . $row['unit'] : '',
        );
    }
}
