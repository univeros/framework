<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\MigrationIntelligence\Planner;

use Altair\MigrationIntelligence\Intent\AddColumnIntent;
use Altair\MigrationIntelligence\Intent\AddForeignKeyIntent;
use Altair\MigrationIntelligence\Intent\AddIndexIntent;
use Altair\MigrationIntelligence\Intent\ChangeColumnIntent;
use Altair\MigrationIntelligence\Intent\DataMigrationIntent;
use Altair\MigrationIntelligence\Intent\DropColumnIntent;
use Altair\MigrationIntelligence\Intent\DropIndexIntent;
use Altair\MigrationIntelligence\Intent\IntentInterface;
use Altair\MigrationIntelligence\Intent\RenameColumnIntent;
use Altair\MigrationIntelligence\Schema\ColumnShape;
use Altair\MigrationIntelligence\Schema\ForeignKeyShape;
use Altair\MigrationIntelligence\Schema\IndexShape;
use Override;

/**
 * Shared preview-SQL rendering. Subclasses supply the dialect-specific bits:
 * identifier quoting, the SQL type for a column, and the `ALTER COLUMN`
 * grammar (which differs sharply across Postgres / MySQL / SQLite).
 */
abstract class AbstractDialectPlanner implements DialectPlanner
{
    #[Override]
    public function forward(IntentInterface $intent): array
    {
        return match (true) {
            $intent instanceof AddColumnIntent => [$this->addColumn($intent)],
            $intent instanceof DropColumnIntent => [$this->dropColumn($intent->table, $intent->column->name)],
            $intent instanceof RenameColumnIntent => [$this->renameColumn($intent->table, $intent->from, $intent->to)],
            $intent instanceof ChangeColumnIntent => $this->alterColumn($intent),
            $intent instanceof AddIndexIntent => [$this->addIndex($intent->table, $intent->index)],
            $intent instanceof DropIndexIntent => [$this->dropIndex($intent->table, $intent->index)],
            $intent instanceof AddForeignKeyIntent => $this->addForeignKey($intent->table, $intent->foreignKey),
            $intent instanceof DataMigrationIntent => [$intent->sql],
            default => [],
        };
    }

    #[Override]
    public function rollback(IntentInterface $intent): array
    {
        return match (true) {
            $intent instanceof AddColumnIntent => [$this->dropColumn($intent->table, $intent->column->name)],
            $intent instanceof DropColumnIntent => [$this->addColumn(new AddColumnIntent($intent->table, $intent->column))],
            $intent instanceof RenameColumnIntent => [$this->renameColumn($intent->table, $intent->to, $intent->from)],
            $intent instanceof AddIndexIntent => [$this->dropIndex($intent->table, $intent->index)],
            $intent instanceof DropIndexIntent => [$this->addIndex($intent->table, $intent->index)],
            default => [],
        };
    }

    abstract protected function quote(string $identifier): string;

    abstract protected function sqlType(ColumnShape $column): string;

    /**
     * @return list<string>
     */
    abstract protected function alterColumn(ChangeColumnIntent $intent): array;

    protected function addColumn(AddColumnIntent $intent): string
    {
        return \sprintf(
            'ALTER TABLE %s ADD COLUMN %s',
            $this->quote($intent->table),
            $this->columnDefinition($intent->column),
        );
    }

    protected function dropColumn(string $table, string $column): string
    {
        return \sprintf('ALTER TABLE %s DROP COLUMN %s', $this->quote($table), $this->quote($column));
    }

    protected function renameColumn(string $table, string $from, string $to): string
    {
        return \sprintf(
            'ALTER TABLE %s RENAME COLUMN %s TO %s',
            $this->quote($table),
            $this->quote($from),
            $this->quote($to),
        );
    }

    protected function addIndex(string $table, IndexShape $index): string
    {
        return \sprintf(
            'CREATE %sINDEX %s ON %s (%s)',
            $index->unique ? 'UNIQUE ' : '',
            $this->quote($this->indexName($table, $index)),
            $this->quote($table),
            $this->quoteList($index->columns),
        );
    }

    protected function dropIndex(string $table, IndexShape $index): string
    {
        return \sprintf('DROP INDEX %s', $this->quote($this->indexName($table, $index)));
    }

    /**
     * @return list<string>
     */
    protected function addForeignKey(string $table, ForeignKeyShape $foreignKey): array
    {
        $sql = \sprintf(
            'ALTER TABLE %s ADD CONSTRAINT %s FOREIGN KEY (%s) REFERENCES %s (%s)',
            $this->quote($table),
            $this->quote($this->foreignKeyName($table, $foreignKey)),
            $this->quoteList($foreignKey->columns),
            $this->quote($foreignKey->foreignTable),
            $this->quoteList($foreignKey->foreignColumns),
        );

        if ($foreignKey->onDelete !== null && $foreignKey->onDelete !== '') {
            $sql .= ' ON DELETE ' . $foreignKey->onDelete;
        }

        return [$sql];
    }

    protected function columnDefinition(ColumnShape $column): string
    {
        $sql = $this->quote($column->name) . ' ' . $this->sqlType($column);
        $sql .= $column->nullable ? ' NULL' : ' NOT NULL';

        if ($column->hasDefault) {
            $sql .= ' DEFAULT ' . $this->defaultLiteral($column);
        }

        return $sql;
    }

    protected function defaultLiteral(ColumnShape $column): string
    {
        return match (true) {
            $column->default === null => 'NULL',
            \is_bool($column->default) => $this->boolLiteral($column->default),
            \is_int($column->default) || \is_float($column->default) => (string) $column->default,
            $column->default === 'now' => 'CURRENT_TIMESTAMP',
            default => "'" . str_replace("'", "''", (string) $column->default) . "'",
        };
    }

    protected function boolLiteral(bool $value): string
    {
        return $value ? '1' : '0';
    }

    protected function indexName(string $table, IndexShape $index): string
    {
        if ($index->name !== null && $index->name !== '') {
            return $index->name;
        }

        return $table . '_' . implode('_', $index->columns) . ($index->unique ? '_unique' : '_index');
    }

    protected function foreignKeyName(string $table, ForeignKeyShape $foreignKey): string
    {
        if ($foreignKey->name !== null && $foreignKey->name !== '') {
            return $foreignKey->name;
        }

        return $table . '_' . implode('_', $foreignKey->columns) . '_fk';
    }

    /**
     * @param list<string> $identifiers
     */
    protected function quoteList(array $identifiers): string
    {
        return implode(', ', array_map($this->quote(...), $identifiers));
    }
}
