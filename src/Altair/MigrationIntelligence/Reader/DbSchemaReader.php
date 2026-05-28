<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\MigrationIntelligence\Reader;

use Altair\MigrationIntelligence\Schema\ColumnShape;
use Altair\MigrationIntelligence\Schema\ColumnType;
use Altair\MigrationIntelligence\Schema\ForeignKeyShape;
use Altair\MigrationIntelligence\Schema\IndexShape;
use Altair\MigrationIntelligence\Schema\TableShape;
use Cycle\Database\DatabaseInterface;

/**
 * Reads the live shape of a table from a Cycle database connection.
 *
 * Returns `null` when the table does not exist, so callers can treat a missing
 * table as "create from scratch" rather than crashing.
 */
final readonly class DbSchemaReader
{
    public function read(DatabaseInterface $database, string $table): ?TableShape
    {
        if ($table === '') {
            return null;
        }

        $schema = $database->table($table);
        if (!$schema->exists()) {
            return null;
        }

        $primaryKeys = $schema->getPrimaryKeys();

        $columns = [];
        foreach ($schema->getColumns() as $column) {
            $size = $column->getSize();
            $columns[] = new ColumnShape(
                name: $column->getName(),
                type: ColumnType::fromCycleAbstract($column->getAbstractType()),
                nullable: $column->isNullable(),
                hasDefault: $column->hasDefaultValue(),
                default: $column->getDefaultValue(),
                primary: \in_array($column->getName(), $primaryKeys, true),
                size: $size > 0 ? $size : null,
            );
        }

        $indexes = [];
        foreach ($schema->getIndexes() as $index) {
            $indexes[] = new IndexShape(
                columns: array_values($index->getColumns()),
                unique: $index->isUnique(),
                name: $index->getName(),
            );
        }

        $foreignKeys = [];
        foreach ($schema->getForeignKeys() as $foreignKey) {
            $foreignKeys[] = new ForeignKeyShape(
                columns: array_values($foreignKey->getColumns()),
                foreignTable: $foreignKey->getForeignTable(),
                foreignColumns: array_values($foreignKey->getForeignKeys()),
                onDelete: $foreignKey->getDeleteRule(),
                onUpdate: $foreignKey->getUpdateRule(),
                name: $foreignKey->getName(),
            );
        }

        return new TableShape($table, $columns, $indexes, $foreignKeys);
    }
}
