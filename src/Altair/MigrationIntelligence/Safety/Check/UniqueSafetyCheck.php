<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\MigrationIntelligence\Safety\Check;

use Altair\MigrationIntelligence\Intent\AddIndexIntent;
use Altair\MigrationIntelligence\Intent\IntentInterface;
use Altair\MigrationIntelligence\Safety\RowCounter;
use Altair\MigrationIntelligence\Safety\SafetyCheckInterface;
use Altair\MigrationIntelligence\Safety\SafetyFinding;
use Override;

/**
 * Flags a new UNIQUE index whose existing data already contains duplicates.
 * Multi-column unique constraints are noted but not row-counted in v1.
 */
final readonly class UniqueSafetyCheck implements SafetyCheckInterface
{
    private const string NAME = 'unique';

    #[Override]
    public function check(IntentInterface $intent, RowCounter $rows): array
    {
        if (!$intent instanceof AddIndexIntent || !$intent->index->unique) {
            return [];
        }

        if (\count($intent->index->columns) !== 1) {
            return [SafetyFinding::info(
                \sprintf(
                    'Cannot pre-verify the multi-column unique index on (%s); confirm there are no duplicate tuples.',
                    implode(', ', $intent->index->columns),
                ),
                self::NAME,
            )];
        }

        $column = $intent->index->columns[0];
        $duplicates = $rows->duplicateGroups($intent->table, $column);
        if ($duplicates === 0) {
            return [];
        }

        return [SafetyFinding::error(
            \sprintf(
                "Adding a UNIQUE index on '%s' will fail: %d value(s) are duplicated. Dedup before applying.",
                $column,
                $duplicates,
            ),
            self::NAME,
        )];
    }
}
