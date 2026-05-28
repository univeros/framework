<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\MigrationIntelligence\Safety\Check;

use Altair\MigrationIntelligence\Intent\DropColumnIntent;
use Altair\MigrationIntelligence\Intent\IntentInterface;
use Altair\MigrationIntelligence\Safety\RowCounter;
use Altair\MigrationIntelligence\Safety\SafetyCheckInterface;
use Altair\MigrationIntelligence\Safety\SafetyFinding;
use Override;

/**
 * Warns on every column drop (it is irreversible) and escalates to an error
 * when the column still holds non-null data, unless the caller forces it.
 */
final readonly class DropColumnSafetyCheck implements SafetyCheckInterface
{
    private const string NAME = 'drop_column';

    public function __construct(private bool $force = false) {}

    #[Override]
    public function check(IntentInterface $intent, RowCounter $rows): array
    {
        if (!$intent instanceof DropColumnIntent) {
            return [];
        }

        $total = $rows->total($intent->table);
        $nulls = $total === 0 ? 0 : $rows->nullCount($intent->table, $intent->column->name);
        $nonNull = $total - $nulls;

        if ($nonNull > 0 && !$this->force) {
            return [SafetyFinding::error(
                \sprintf(
                    "Dropping '%s' destroys %d non-null value(s); this cannot be undone. Re-run with --force to proceed.",
                    $intent->column->name,
                    $nonNull,
                ),
                self::NAME,
            )];
        }

        return [SafetyFinding::warn(
            \sprintf("Dropping '%s' is irreversible; ensure nothing depends on this column.", $intent->column->name),
            self::NAME,
        )];
    }
}
