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
use Altair\MigrationIntelligence\Intent\IntentInterface;
use Altair\MigrationIntelligence\Plan\MigrationPlan;
use Altair\MigrationIntelligence\Plan\PlanSet;
use Override;

/**
 * Deterministic JSON form of a plan — the MCP-facing output. Stable for the
 * same inputs (timestamps are injected into names by the builder).
 */
final readonly class JsonRenderer implements PlanRendererInterface
{
    #[Override]
    public function render(PlanSet $plan): string
    {
        return json_encode($this->toArray($plan), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(PlanSet $plan): array
    {
        return [
            'table' => $plan->table,
            'two_phase' => $plan->twoPhase,
            'migrations' => array_map($this->migration(...), $plan->migrations),
            'safety' => [
                'skipped' => $plan->safety->skipped,
                'skip_reason' => $plan->safety->skipReason,
                'has_errors' => $plan->safety->hasErrors(),
                'has_warnings' => $plan->safety->hasWarnings(),
                'findings' => $plan->safety->toArray(),
            ],
            'exit_code' => $plan->exitCode(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function migration(MigrationPlan $migration): array
    {
        return [
            'migration_name' => $migration->name,
            'class_name' => $migration->className,
            'filename' => $migration->filename,
            'dialect' => $migration->dialect,
            'phase' => $migration->phase,
            'operations' => array_map($this->operation(...), $migration->operations),
            'forward_sql' => $migration->forwardSql,
            'rollback_sql' => $migration->rollbackSql,
        ];
    }

    /**
     * @return array{op: string, table: string, describe: string}
     */
    private function operation(IntentInterface $intent): array
    {
        return [
            'op' => $intent->kind()->value,
            'table' => $intent->table(),
            'describe' => $intent->describe(),
        ];
    }
}
