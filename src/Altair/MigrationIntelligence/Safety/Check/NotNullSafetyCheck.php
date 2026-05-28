<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\MigrationIntelligence\Safety\Check;

use Altair\MigrationIntelligence\Intent\AddColumnIntent;
use Altair\MigrationIntelligence\Intent\ChangeColumnIntent;
use Altair\MigrationIntelligence\Intent\IntentInterface;
use Altair\MigrationIntelligence\Safety\RowCounter;
use Altair\MigrationIntelligence\Safety\SafetyCheckInterface;
use Altair\MigrationIntelligence\Safety\SafetyFinding;
use Override;

/**
 * Flags NOT NULL changes that would fail against existing rows: adding a
 * NOT NULL column without a default, or tightening an existing column whose
 * data still contains NULLs.
 */
final readonly class NotNullSafetyCheck implements SafetyCheckInterface
{
    private const string NAME = 'not_null';

    #[Override]
    public function check(IntentInterface $intent, RowCounter $rows): array
    {
        if ($intent instanceof AddColumnIntent) {
            return $this->checkAddColumn($intent, $rows);
        }

        if ($intent instanceof ChangeColumnIntent && $intent->tightensNullability()) {
            return $this->checkTighten($intent, $rows);
        }

        return [];
    }

    /**
     * @return list<SafetyFinding>
     */
    private function checkAddColumn(AddColumnIntent $intent, RowCounter $rows): array
    {
        if ($intent->column->nullable || $intent->column->hasDefault) {
            return [];
        }

        $existing = $rows->total($intent->table);
        if ($existing === 0) {
            return [];
        }

        return [SafetyFinding::error(
            \sprintf(
                "Adding NOT NULL column '%s' without a default to %d existing row(s) will fail. "
                . 'Add it nullable with a backfill, or give it a default.',
                $intent->column->name,
                $existing,
            ),
            self::NAME,
        )];
    }

    /**
     * @return list<SafetyFinding>
     */
    private function checkTighten(ChangeColumnIntent $intent, RowCounter $rows): array
    {
        $nulls = $rows->nullCount($intent->table, $intent->after->name);
        if ($nulls === 0) {
            return [];
        }

        return [SafetyFinding::error(
            \sprintf(
                "Setting NOT NULL on '%s' will fail: %d existing row(s) hold NULL. Backfill them first.",
                $intent->after->name,
                $nulls,
            ),
            self::NAME,
        )];
    }
}
