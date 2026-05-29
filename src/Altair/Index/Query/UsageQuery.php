<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Index\Query;

use Altair\Index\Model\Symbol;
use Altair\Index\Model\Usage;
use Altair\Index\Model\UsageKind;
use Altair\Index\Storage\RowMapper;
use PDO;

/**
 * The read side of the index: the find-usages, implementers, extenders,
 * callers-of, and dead-code queries. Every method is a pure read; nothing here
 * mutates the database.
 */
final readonly class UsageQuery
{
    public function __construct(private PDO $pdo) {}

    /**
     * Every recorded reference to a symbol, ordered by location.
     *
     * @return list<Usage>
     */
    public function usages(string $fqn): array
    {
        $statement = $this->pdo->prepare(
            'SELECT target_fqn, used_in_file, used_in_line, usage_kind, context
             FROM usages WHERE target_fqn = :fqn
             ORDER BY used_in_file, used_in_line',
        );
        $statement->execute([':fqn' => $fqn]);

        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll();

        return array_values(array_map(RowMapper::usage(...), $rows));
    }

    /**
     * Classes that declare `implements <interface>`.
     *
     * @return list<string>
     */
    public function implementers(string $interface): array
    {
        return $this->subjects(UsageKind::Implements_, $interface);
    }

    /**
     * Classes (or interfaces) that declare `extends <class>`.
     *
     * @return list<string>
     */
    public function extenders(string $class): array
    {
        return $this->subjects(UsageKind::Extends_, $class);
    }

    /**
     * Call sites of a method, each carrying the calling scope as context.
     *
     * @return list<Usage>
     */
    public function callers(string $method): array
    {
        $statement = $this->pdo->prepare(
            'SELECT target_fqn, used_in_file, used_in_line, usage_kind, context
             FROM usages WHERE usage_kind = :kind AND target_fqn = :fqn
             ORDER BY used_in_file, used_in_line',
        );
        $statement->execute([':kind' => UsageKind::Call->value, ':fqn' => $method]);

        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll();

        return array_values(array_map(RowMapper::usage(...), $rows));
    }

    /**
     * Declared symbols with zero recorded references — dead-code candidates.
     *
     * @return list<Symbol>
     */
    public function unused(): array
    {
        $statement = $this->pdo->prepare(
            'SELECT s.fqn, s.kind, s.file, s.line, s.visibility, s.is_readonly, s.is_static
             FROM symbols s
             WHERE NOT EXISTS (SELECT 1 FROM usages u WHERE u.target_fqn = s.fqn)
             ORDER BY s.file, s.line',
        );
        $statement->execute();

        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll();

        return array_values(array_map(RowMapper::symbol(...), $rows));
    }

    /**
     * @return list<string>
     */
    private function subjects(UsageKind $kind, string $target): array
    {
        $statement = $this->pdo->prepare(
            'SELECT DISTINCT context FROM usages
             WHERE usage_kind = :kind AND target_fqn = :fqn AND context IS NOT NULL
             ORDER BY context',
        );
        $statement->execute([':kind' => $kind->value, ':fqn' => $target]);

        /** @var list<array{context: ?string}> $rows */
        $rows = $statement->fetchAll();

        return array_values(array_filter(array_map(
            static fn(array $row): string => (string) $row['context'],
            $rows,
        )));
    }
}
