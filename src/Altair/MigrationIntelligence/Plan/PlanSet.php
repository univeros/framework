<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\MigrationIntelligence\Plan;

use Altair\MigrationIntelligence\Safety\SafetyReport;

/**
 * The full result of planning a migration: one plan normally, two for a
 * two-phase change (rename), plus the shared safety report.
 */
final readonly class PlanSet
{
    /**
     * @param list<MigrationPlan> $migrations
     */
    public function __construct(
        public string $table,
        public array $migrations,
        public SafetyReport $safety,
        public bool $twoPhase = false,
    ) {}

    public function isEmpty(): bool
    {
        foreach ($this->migrations as $migration) {
            if (!$migration->isEmpty()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Exit non-zero when the plan is unsafe, so CI can gate on it.
     */
    public function exitCode(): int
    {
        return $this->safety->hasErrors() ? 1 : 0;
    }
}
