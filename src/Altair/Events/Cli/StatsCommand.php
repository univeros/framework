<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Events\Cli;

use Altair\Cli\Attribute\Command;
use Altair\Cli\Attribute\Option;
use Altair\Events\Reader;

/**
 * `bin/altair events:stats` — aggregate counts by kind/status + total wall
 * time. Useful as a "what was this hour" answer.
 */
#[Command(
    name: 'events:stats',
    description: 'Print aggregate counts and total duration across all events.',
)]
final readonly class StatsCommand
{
    public function __construct(
        private Reader $reader,
    ) {}

    public function __invoke(
        #[Option(description: 'Output format: human or json.')]
        string $format = 'human',
    ): int {
        $stats = $this->reader->stats();

        if ($format === 'json') {
            echo json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";

            return 0;
        }

        echo \sprintf('Events: %d%s', $stats['total'], PHP_EOL);
        echo "First:  " . ($stats['first_at'] ?? '(none)') . "\n";
        echo "Last:   " . ($stats['last_at'] ?? '(none)') . "\n";
        echo "Total duration: {$stats['total_duration_ms']}ms\n";

        if ($stats['by_kind'] !== []) {
            echo "\nBy kind:\n";
            foreach ($stats['by_kind'] as $kind => $count) {
                echo \sprintf("  %-20s %d\n", $kind, $count);
            }
        }

        if ($stats['by_status'] !== []) {
            echo "\nBy status:\n";
            foreach ($stats['by_status'] as $status => $count) {
                echo \sprintf("  %-20s %d\n", $status, $count);
            }
        }

        return 0;
    }
}
