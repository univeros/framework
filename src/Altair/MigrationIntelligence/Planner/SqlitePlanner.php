<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\MigrationIntelligence\Planner;

use Altair\MigrationIntelligence\Intent\ChangeColumnIntent;
use Altair\MigrationIntelligence\Schema\ColumnShape;
use Altair\MigrationIntelligence\Schema\ColumnType;
use Altair\MigrationIntelligence\Schema\ForeignKeyShape;
use Override;

/**
 * SQLite preview planner.
 *
 * SQLite's `ALTER TABLE` cannot change a column's type/constraints or add a
 * foreign key in place — those require a 12-step table rebuild. Rather than
 * emit fragile rebuild SQL in the preview, we surface a note; the emitted Cycle
 * migration performs the rebuild correctly via its `alterColumn()` operation.
 */
final class SqlitePlanner extends AbstractDialectPlanner
{
    public const string NAME = 'sqlite';

    #[Override]
    public function name(): string
    {
        return self::NAME;
    }

    #[Override]
    protected function quote(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }

    #[Override]
    protected function sqlType(ColumnShape $column): string
    {
        return match ($column->type) {
            ColumnType::PRIMARY, ColumnType::BIG_PRIMARY => 'INTEGER PRIMARY KEY AUTOINCREMENT',
            ColumnType::INTEGER, ColumnType::BIG_INTEGER, ColumnType::BOOLEAN => 'INTEGER',
            ColumnType::STRING, ColumnType::ENUM => 'VARCHAR(' . ($column->size ?? 255) . ')',
            ColumnType::UUID => 'VARCHAR(36)',
            ColumnType::TEXT, ColumnType::JSON => 'TEXT',
            ColumnType::FLOAT => 'REAL',
            ColumnType::DECIMAL => 'NUMERIC',
            ColumnType::DATE => 'DATE',
            ColumnType::TIME => 'TIME',
            ColumnType::DATETIME, ColumnType::TIMESTAMP => 'DATETIME',
            default => 'TEXT',
        };
    }

    #[Override]
    protected function alterColumn(ChangeColumnIntent $intent): array
    {
        return [\sprintf(
            '-- sqlite: ALTER COLUMN %s is unsupported in place; the emitted Cycle migration rebuilds the table via alterColumn().',
            $intent->after->name,
        )];
    }

    #[Override]
    protected function addForeignKey(string $table, ForeignKeyShape $foreignKey): array
    {
        return [\sprintf(
            '-- sqlite: ADD FOREIGN KEY on %s (%s) is unsupported in place; the emitted Cycle migration rebuilds the table.',
            $table,
            implode(', ', $foreignKey->columns),
        )];
    }
}
