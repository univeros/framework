<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\MigrationIntelligence\Schema;

/**
 * The normalized, dialect-agnostic shape of a single table.
 *
 * Every reader (spec / live DB / entity reflection) produces this same shape,
 * and {@see \Altair\MigrationIntelligence\Diff\SchemaDiffer} diffs two of them.
 */
final readonly class TableShape
{
    /**
     * @param list<ColumnShape>     $columns
     * @param list<IndexShape>      $indexes
     * @param list<ForeignKeyShape> $foreignKeys
     */
    public function __construct(
        public string $name,
        public array $columns = [],
        public array $indexes = [],
        public array $foreignKeys = [],
    ) {}

    public function column(string $name): ?ColumnShape
    {
        foreach ($this->columns as $column) {
            if ($column->name === $name) {
                return $column;
            }
        }

        return null;
    }

    public function hasColumn(string $name): bool
    {
        return $this->column($name) instanceof ColumnShape;
    }

    /**
     * @return list<string>
     */
    public function columnNames(): array
    {
        return array_map(static fn(ColumnShape $column): string => $column->name, $this->columns);
    }

    public function index(string $key): ?IndexShape
    {
        foreach ($this->indexes as $index) {
            if ($index->key() === $key) {
                return $index;
            }
        }

        return null;
    }

    public function foreignKey(string $key): ?ForeignKeyShape
    {
        foreach ($this->foreignKeys as $foreignKey) {
            if ($foreignKey->key() === $key) {
                return $foreignKey;
            }
        }

        return null;
    }
}
