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
use Override;

final class PostgresPlanner extends AbstractDialectPlanner
{
    public const string NAME = 'postgres';

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
            ColumnType::PRIMARY => 'SERIAL',
            ColumnType::BIG_PRIMARY => 'BIGSERIAL',
            ColumnType::INTEGER => 'INTEGER',
            ColumnType::BIG_INTEGER => 'BIGINT',
            ColumnType::STRING => 'VARCHAR(' . ($column->size ?? 255) . ')',
            ColumnType::ENUM, ColumnType::UUID => $column->type === ColumnType::UUID ? 'UUID' : 'VARCHAR(255)',
            ColumnType::TEXT => 'TEXT',
            ColumnType::BOOLEAN => 'BOOLEAN',
            ColumnType::FLOAT => 'DOUBLE PRECISION',
            ColumnType::DECIMAL => 'DECIMAL',
            ColumnType::DATE => 'DATE',
            ColumnType::TIME => 'TIME',
            ColumnType::DATETIME, ColumnType::TIMESTAMP => 'TIMESTAMP',
            ColumnType::JSON => 'JSONB',
            default => 'VARCHAR(255)',
        };
    }

    #[Override]
    protected function boolLiteral(bool $value): string
    {
        return $value ? 'TRUE' : 'FALSE';
    }

    #[Override]
    protected function alterColumn(ChangeColumnIntent $intent): array
    {
        $table = $this->quote($intent->table);
        $column = $this->quote($intent->after->name);
        $statements = [];

        if ($intent->typeChanged()) {
            $statements[] = \sprintf(
                'ALTER TABLE %s ALTER COLUMN %s TYPE %s',
                $table,
                $column,
                $this->sqlType($intent->after),
            );
        }

        if ($intent->before->nullable !== $intent->after->nullable) {
            $statements[] = \sprintf(
                'ALTER TABLE %s ALTER COLUMN %s %s NOT NULL',
                $table,
                $column,
                $intent->after->nullable ? 'DROP' : 'SET',
            );
        }

        if ($intent->before->hasDefault !== $intent->after->hasDefault || $intent->before->default !== $intent->after->default) {
            $statements[] = $intent->after->hasDefault
                ? \sprintf('ALTER TABLE %s ALTER COLUMN %s SET DEFAULT %s', $table, $column, $this->defaultLiteral($intent->after))
                : \sprintf('ALTER TABLE %s ALTER COLUMN %s DROP DEFAULT', $table, $column);
        }

        return $statements;
    }
}
