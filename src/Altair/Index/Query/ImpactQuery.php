<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Index\Query;

use Altair\Index\Support\FileType;
use PDO;

/**
 * Computes the refactor-impact of changing a set of symbols.
 *
 * A symbol's impact includes references to the symbol itself and to any of its
 * members (`Class::method`, `Class::$prop`, `Class::CONST`) — so asking about a
 * class enumerates every file touching the class or its API. Members are
 * matched with SQLite `GLOB` (whose only metacharacters are `* ? [`, none of
 * which appear in a fully-qualified PHP name), keeping the match exact.
 */
final readonly class ImpactQuery
{
    public function __construct(private PDO $pdo) {}

    /**
     * @param list<string> $fqns
     */
    public function impact(array $fqns): ImpactReport
    {
        $fqns = array_values(array_unique(array_filter($fqns, static fn(string $f): bool => $f !== '')));
        if ($fqns === []) {
            return new ImpactReport([], 0, 0, 0, [], [], []);
        }

        $counts = $this->countByFile($fqns);

        $byFile = [];
        $tests = [];
        $specs = [];
        foreach ($counts as $file => $usages) {
            $byFile[] = ['file' => $file, 'usages' => $usages];
            if (FileType::isTest($file)) {
                $tests[] = $file;
            } elseif (FileType::isSpec($file)) {
                $specs[] = $file;
            }
        }

        usort($byFile, static fn(array $a, array $b): int => $b['usages'] <=> $a['usages'] ?: strcmp($a['file'], $b['file']));
        sort($tests);
        sort($specs);

        return new ImpactReport(
            $fqns,
            \count($counts),
            \count($tests),
            \count($specs),
            $byFile,
            $tests,
            $specs,
        );
    }

    /**
     * @param list<string> $fqns
     *
     * @return array<string, int>
     */
    private function countByFile(array $fqns): array
    {
        $clauses = [];
        $params = [];
        foreach ($fqns as $i => $fqn) {
            $clauses[] = \sprintf('target_fqn = :exact%d OR target_fqn GLOB :glob%d', $i, $i);
            $params[':exact' . $i] = $fqn;
            $params[':glob' . $i] = $fqn . '::*';
        }

        $sql = 'SELECT used_in_file, COUNT(*) AS usages FROM usages WHERE '
            . implode(' OR ', array_map(static fn(string $c): string => '(' . $c . ')', $clauses))
            . ' GROUP BY used_in_file';

        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        /** @var list<array{used_in_file: string, usages: int|string}> $rows */
        $rows = $statement->fetchAll();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(string) $row['used_in_file']] = (int) $row['usages'];
        }

        return $counts;
    }
}
