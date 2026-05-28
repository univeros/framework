<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\MigrationIntelligence\Planner;

use Altair\MigrationIntelligence\Exception\MigrationIntelligenceException;

/**
 * Resolves a {@see DialectPlanner} by driver name, with aliases for the names
 * Cycle's drivers report (e.g. `postgresql`, `sqlite3`).
 */
final readonly class PlannerRegistry
{
    /**
     * @var array<string, DialectPlanner>
     */
    private array $planners;

    /**
     * @param list<DialectPlanner> $planners defaults to Postgres + MySQL + SQLite
     */
    public function __construct(array $planners = [])
    {
        if ($planners === []) {
            $planners = [new PostgresPlanner(), new MySqlPlanner(), new SqlitePlanner()];
        }

        $map = [];
        foreach ($planners as $planner) {
            $map[$planner->name()] = $planner;
        }

        $this->planners = $map;
    }

    public function get(string $driver): DialectPlanner
    {
        $name = $this->normalize($driver);

        return $this->planners[$name]
            ?? throw new MigrationIntelligenceException(\sprintf(
                "No planner for driver '%s'. Known: %s.",
                $driver,
                implode(', ', array_keys($this->planners)),
            ));
    }

    public function has(string $driver): bool
    {
        return isset($this->planners[$this->normalize($driver)]);
    }

    private function normalize(string $driver): string
    {
        return match (strtolower($driver)) {
            'postgres', 'postgresql', 'pgsql', 'pg' => PostgresPlanner::NAME,
            'mysql', 'mariadb' => MySqlPlanner::NAME,
            'sqlite', 'sqlite3' => SqlitePlanner::NAME,
            default => strtolower($driver),
        };
    }
}
