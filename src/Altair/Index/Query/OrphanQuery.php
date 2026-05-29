<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Index\Query;

use PDO;

/**
 * Surfaces structural orphans the index can detect without leaving the
 * database: spec endpoints and entities that name a class which is never
 * declared anywhere in the indexed source (typically a spec that has not been
 * scaffolded yet, or a class that was renamed/deleted out from under its spec).
 */
final readonly class OrphanQuery
{
    public function __construct(private PDO $pdo) {}

    /**
     * @return list<array{fqn: string, usage_kind: string, file: string}>
     */
    public function danglingSpecTargets(): array
    {
        $statement = $this->pdo->prepare(
            "SELECT DISTINCT u.target_fqn AS fqn, u.usage_kind, u.used_in_file AS file
             FROM usages u
             WHERE u.usage_kind IN ('spec_endpoint', 'spec_entity')
               AND NOT EXISTS (SELECT 1 FROM symbols s WHERE s.fqn = u.target_fqn)
             ORDER BY u.used_in_file, u.target_fqn",
        );
        $statement->execute();

        /** @var list<array{fqn: string, usage_kind: string, file: string}> $rows */
        $rows = $statement->fetchAll();

        return array_values(array_map(
            static fn(array $row): array => [
                'fqn' => (string) $row['fqn'],
                'usage_kind' => (string) $row['usage_kind'],
                'file' => (string) $row['file'],
            ],
            $rows,
        ));
    }
}
