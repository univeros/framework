<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\MigrationIntelligence\Safety\Check;

use Altair\MigrationIntelligence\Intent\DataMigrationIntent;
use Altair\MigrationIntelligence\Intent\IntentInterface;
use Altair\MigrationIntelligence\Safety\RowCounter;
use Altair\MigrationIntelligence\Safety\SafetyCheckInterface;
use Altair\MigrationIntelligence\Safety\SafetyFinding;
use Override;

/**
 * Warns when a structural change targets a large table, where the operation may
 * lock the table for an unacceptable time. The runner dedups the per-table
 * warning so it appears once, not once per intent.
 */
final readonly class LargeTableSafetyCheck implements SafetyCheckInterface
{
    public const int DEFAULT_THRESHOLD = 1_000_000;

    private const string NAME = 'large_table';

    public function __construct(private int $threshold = self::DEFAULT_THRESHOLD) {}

    #[Override]
    public function check(IntentInterface $intent, RowCounter $rows): array
    {
        if ($intent instanceof DataMigrationIntent) {
            return [];
        }

        $total = $rows->total($intent->table());
        if ($total < $this->threshold) {
            return [];
        }

        return [SafetyFinding::warn(
            \sprintf(
                "Table '%s' has %d rows; this change may hold a long lock. Run it in a maintenance window "
                . '(or with CONCURRENTLY on Postgres).',
                $intent->table(),
                $total,
            ),
            self::NAME,
        )];
    }
}
