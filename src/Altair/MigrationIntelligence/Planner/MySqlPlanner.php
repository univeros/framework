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
use Altair\MigrationIntelligence\Schema\IndexShape;
use Override;

final class MySqlPlanner extends AbstractDialectPlanner
{
    public const string NAME = 'mysql';

    #[Override]
    public function name(): string
    {
        return self::NAME;
    }

    #[Override]
    protected function quote(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    #[Override]
    protected function sqlType(ColumnShape $column): string
    {
        return match ($column->type) {
            ColumnType::PRIMARY => 'INT AUTO_INCREMENT',
            ColumnType::BIG_PRIMARY => 'BIGINT AUTO_INCREMENT',
            ColumnType::INTEGER => 'INT',
            ColumnType::BIG_INTEGER => 'BIGINT',
            ColumnType::STRING, ColumnType::ENUM => 'VARCHAR(' . ($column->size ?? 255) . ')',
            ColumnType::UUID => 'CHAR(36)',
            ColumnType::TEXT => 'TEXT',
            ColumnType::BOOLEAN => 'TINYINT(1)',
            ColumnType::FLOAT => 'DOUBLE',
            ColumnType::DECIMAL => 'DECIMAL',
            ColumnType::DATE => 'DATE',
            ColumnType::TIME => 'TIME',
            ColumnType::DATETIME => 'DATETIME',
            ColumnType::TIMESTAMP => 'TIMESTAMP',
            ColumnType::JSON => 'JSON',
            default => 'VARCHAR(255)',
        };
    }

    #[Override]
    protected function alterColumn(ChangeColumnIntent $intent): array
    {
        return [\sprintf(
            'ALTER TABLE %s MODIFY COLUMN %s',
            $this->quote($intent->table),
            $this->columnDefinition($intent->after),
        )];
    }

    #[Override]
    protected function dropIndex(string $table, IndexShape $index): string
    {
        return \sprintf(
            'DROP INDEX %s ON %s',
            $this->quote($this->indexName($table, $index)),
            $this->quote($table),
        );
    }
}
