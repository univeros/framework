<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\MigrationIntelligence\Diff;

use Altair\MigrationIntelligence\Intent\AddColumnIntent;
use Altair\MigrationIntelligence\Intent\AddForeignKeyIntent;
use Altair\MigrationIntelligence\Intent\AddIndexIntent;
use Altair\MigrationIntelligence\Intent\ChangeColumnIntent;
use Altair\MigrationIntelligence\Intent\DropColumnIntent;
use Altair\MigrationIntelligence\Intent\DropIndexIntent;
use Altair\MigrationIntelligence\Intent\IntentInterface;
use Altair\MigrationIntelligence\Intent\RenameColumnIntent;
use Altair\MigrationIntelligence\Schema\ColumnShape;
use Altair\MigrationIntelligence\Schema\ForeignKeyShape;
use Altair\MigrationIntelligence\Schema\IndexShape;
use Altair\MigrationIntelligence\Schema\TableShape;

/**
 * Diffs two normalized table shapes into an ordered list of intents.
 *
 * Renames cannot be inferred from a pure structural diff (a drop + add of an
 * identically-typed column is indistinguishable from a rename), so the caller
 * declares them via a `from => to` map; everything else is derived.
 *
 * Emission order is deterministic and migration-safe: renames, adds, changes,
 * index adds, foreign-key adds, index drops, column drops.
 */
final readonly class SchemaDiffer
{
    /**
     * @param array<string, string> $renames map of old column name => new name
     *
     * @return list<IntentInterface>
     */
    public function diff(TableShape $from, TableShape $to, array $renames = []): array
    {
        $renamed = [];
        $renameIntents = [];
        $renameChangeIntents = [];

        foreach ($renames as $old => $new) {
            $fromColumn = $from->column($old);
            $toColumn = $to->column($new);
            if (!$fromColumn instanceof ColumnShape) {
                continue;
            }

            if (!$toColumn instanceof ColumnShape) {
                continue;
            }

            $renamed[$old] = $new;
            $renameIntents[] = new RenameColumnIntent($to->name, $old, $new, $toColumn);

            $renamedFrom = $fromColumn->withName($new);
            if ($renamedFrom->definitionDiffersFrom($toColumn)) {
                $renameChangeIntents[] = new ChangeColumnIntent(
                    $to->name,
                    $renamedFrom,
                    $toColumn,
                    TypeCompatibility::isIncompatible($renamedFrom, $toColumn),
                );
            }
        }

        $addIntents = [];
        $changeIntents = [];
        foreach ($to->columns as $column) {
            if (\in_array($column->name, $renamed, true)) {
                continue;
            }

            $existing = $from->column($column->name);
            if (!$existing instanceof ColumnShape) {
                $addIntents[] = new AddColumnIntent($to->name, $column);

                continue;
            }

            if ($existing->definitionDiffersFrom($column)) {
                $changeIntents[] = new ChangeColumnIntent(
                    $to->name,
                    $existing,
                    $column,
                    TypeCompatibility::isIncompatible($existing, $column),
                );
            }
        }

        $dropIntents = [];
        foreach ($from->columns as $column) {
            if (isset($renamed[$column->name])) {
                continue;
            }

            if ($to->hasColumn($column->name)) {
                continue;
            }

            $dropIntents[] = new DropColumnIntent($to->name, $column);
        }

        $addIndexIntents = [];
        foreach ($to->indexes as $index) {
            if (!$from->index($index->key()) instanceof IndexShape) {
                $addIndexIntents[] = new AddIndexIntent($to->name, $index);
            }
        }

        $dropIndexIntents = [];
        foreach ($from->indexes as $index) {
            if (!$to->index($index->key()) instanceof IndexShape) {
                $dropIndexIntents[] = new DropIndexIntent($to->name, $index);
            }
        }

        $addForeignKeyIntents = [];
        foreach ($to->foreignKeys as $foreignKey) {
            if (!$from->foreignKey($foreignKey->key()) instanceof ForeignKeyShape) {
                $addForeignKeyIntents[] = new AddForeignKeyIntent($to->name, $foreignKey);
            }
        }

        return [
            ...$renameIntents,
            ...$renameChangeIntents,
            ...$addIntents,
            ...$changeIntents,
            ...$addIndexIntents,
            ...$addForeignKeyIntents,
            ...$dropIndexIntents,
            ...$dropIntents,
        ];
    }
}
