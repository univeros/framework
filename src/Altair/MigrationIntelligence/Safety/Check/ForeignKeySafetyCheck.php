<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\MigrationIntelligence\Safety\Check;

use Altair\MigrationIntelligence\Intent\AddForeignKeyIntent;
use Altair\MigrationIntelligence\Intent\IntentInterface;
use Altair\MigrationIntelligence\Safety\RowCounter;
use Altair\MigrationIntelligence\Safety\SafetyCheckInterface;
use Altair\MigrationIntelligence\Safety\SafetyFinding;
use Override;

/**
 * Flags a new foreign key whose existing rows reference a non-existent parent
 * (orphans), which would make the constraint creation fail.
 */
final readonly class ForeignKeySafetyCheck implements SafetyCheckInterface
{
    private const string NAME = 'foreign_key';

    #[Override]
    public function check(IntentInterface $intent, RowCounter $rows): array
    {
        if (!$intent instanceof AddForeignKeyIntent) {
            return [];
        }

        $foreignKey = $intent->foreignKey;
        if (\count($foreignKey->columns) !== 1 || \count($foreignKey->foreignColumns) !== 1) {
            return [SafetyFinding::info(
                'Cannot pre-verify a composite foreign key; confirm there are no orphan rows.',
                self::NAME,
            )];
        }

        $orphans = $rows->orphanCount(
            $intent->table,
            $foreignKey->columns[0],
            $foreignKey->foreignTable,
            $foreignKey->foreignColumns[0],
        );

        if ($orphans === 0) {
            return [];
        }

        return [SafetyFinding::error(
            \sprintf(
                "Adding the foreign key on '%s' will fail: %d row(s) reference a missing %s. Reconcile them first.",
                $foreignKey->columns[0],
                $orphans,
                $foreignKey->foreignTable,
            ),
            self::NAME,
        )];
    }
}
