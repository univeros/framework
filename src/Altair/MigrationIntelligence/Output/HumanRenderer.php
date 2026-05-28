<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\MigrationIntelligence\Output;

use Altair\MigrationIntelligence\Contracts\PlanRendererInterface;
use Altair\MigrationIntelligence\Plan\MigrationPlan;
use Altair\MigrationIntelligence\Plan\PlanSet;
use Altair\MigrationIntelligence\Safety\SafetyFinding;
use Override;

/**
 * Pretty, reviewer-friendly rendering of a plan: each migration's operations
 * and rollback as preview SQL, followed by the safety section.
 */
final readonly class HumanRenderer implements PlanRendererInterface
{
    #[Override]
    public function render(PlanSet $plan): string
    {
        if ($plan->isEmpty()) {
            return \sprintf("No changes: '%s' already matches the desired shape.\n", $plan->table);
        }

        $lines = [];
        $suffix = $plan->twoPhase ? ' (two-phase)' : '';
        $lines[] = \sprintf("Proposed migration for table '%s'%s:", $plan->table, $suffix);
        $lines[] = '';

        foreach ($plan->migrations as $migration) {
            $this->appendMigration($lines, $migration);
        }

        $this->appendSafety($lines, $plan);

        return implode("\n", $lines) . "\n";
    }

    /**
     * @param list<string> $lines
     */
    private function appendMigration(array &$lines, MigrationPlan $migration): void
    {
        $lines[] = \sprintf('%s  [%s]', $migration->filename, $migration->dialect);

        $lines[] = '  Operations:';
        foreach ($migration->forwardSql as $sql) {
            $lines[] = '    -> ' . $sql;
        }

        if ($migration->rollbackSql !== []) {
            $lines[] = '  Rollback:';
            foreach ($migration->rollbackSql as $sql) {
                $lines[] = '    -> ' . $sql;
            }
        }

        $lines[] = '';
    }

    /**
     * @param list<string> $lines
     */
    private function appendSafety(array &$lines, PlanSet $plan): void
    {
        $lines[] = 'Safety:';

        if ($plan->safety->skipped) {
            $lines[] = '  (skipped) ' . ($plan->safety->skipReason ?? '');

            return;
        }

        if ($plan->safety->findings === []) {
            $lines[] = '  [ok] no issues found';

            return;
        }

        foreach ($plan->safety->findings as $finding) {
            $lines[] = \sprintf('  [%s] %s', $this->label($finding), $finding->message);
        }
    }

    private function label(SafetyFinding $finding): string
    {
        return $finding->severity->value;
    }
}
