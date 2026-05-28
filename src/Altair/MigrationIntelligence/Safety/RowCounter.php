<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\MigrationIntelligence\Safety;

use Cycle\Database\DatabaseInterface;

/**
 * Read-only counting queries against the live database, shared by the safety
 * checks. Identifiers are validated and quoted; values are never interpolated.
 */
final readonly class RowCounter
{
    private IdentifierQuoter $quoter;

    public function __construct(private DatabaseInterface $database)
    {
        $this->quoter = IdentifierQuoter::forDriver($database->getDriver()->getType());
    }

    public function total(string $table): int
    {
        return $this->database->select()->from($table)->count();
    }

    public function nullCount(string $table, string $column): int
    {
        return $this->database->select()->from($table)->where($column, '=', null)->count();
    }

    public function duplicateGroups(string $table, string $column): int
    {
        $quotedTable = $this->quoter->quote($table);
        $quotedColumn = $this->quoter->quote($column);

        $sql = 'SELECT COUNT(*) AS c FROM (SELECT ' . $quotedColumn . ' FROM ' . $quotedTable
            . ' WHERE ' . $quotedColumn . ' IS NOT NULL GROUP BY ' . $quotedColumn . ' HAVING COUNT(*) > 1) sub';

        return $this->fetchCount($sql);
    }

    public function orphanCount(string $childTable, string $childColumn, string $parentTable, string $parentColumn): int
    {
        $quotedChildColumn = $this->quoter->quote($childColumn);

        $sql = 'SELECT COUNT(*) AS c FROM ' . $this->quoter->quote($childTable)
            . ' WHERE ' . $quotedChildColumn . ' IS NOT NULL AND ' . $quotedChildColumn
            . ' NOT IN (SELECT ' . $this->quoter->quote($parentColumn) . ' FROM ' . $this->quoter->quote($parentTable) . ')';

        return $this->fetchCount($sql);
    }

    /**
     * @return list<mixed>
     */
    public function sample(string $table, string $column, int $limit = 100): array
    {
        $rows = $this->database->select([$column])
            ->from($table)
            ->where($column, '!=', null)
            ->limit($limit)
            ->fetchAll();

        return array_values(array_map(static fn(array $row): mixed => $row[$column] ?? null, $rows));
    }

    /**
     * @param non-empty-string $sql
     */
    private function fetchCount(string $sql): int
    {
        $row = $this->database->query($sql)->fetch();

        return (int) ($row['c'] ?? 0);
    }
}
