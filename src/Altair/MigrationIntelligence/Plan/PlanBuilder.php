<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\MigrationIntelligence\Plan;

use Altair\MigrationIntelligence\Diff\SchemaDiffer;
use Altair\MigrationIntelligence\Intent\AddColumnIntent;
use Altair\MigrationIntelligence\Intent\ChangeColumnIntent;
use Altair\MigrationIntelligence\Intent\DataMigrationIntent;
use Altair\MigrationIntelligence\Intent\DropColumnIntent;
use Altair\MigrationIntelligence\Intent\IntentInterface;
use Altair\MigrationIntelligence\Intent\RenameColumnIntent;
use Altair\MigrationIntelligence\Planner\DialectPlanner;
use Altair\MigrationIntelligence\Planner\PlannerRegistry;
use Altair\MigrationIntelligence\Safety\IdentifierQuoter;
use Altair\MigrationIntelligence\Safety\SafetyRunner;
use Altair\MigrationIntelligence\Schema\ColumnShape;

/**
 * Turns a current/desired table pair into a {@see PlanSet}.
 *
 * Renames are expanded into a safe two-phase deployment: phase 1 adds the new
 * column (nullable) and copies the data; phase 2 enforces the final
 * constraints and drops the old column. Everything else is a single migration.
 */
final readonly class PlanBuilder
{
    public function __construct(
        private SchemaDiffer $differ = new SchemaDiffer(),
        private PlannerRegistry $planners = new PlannerRegistry(),
        private PlanNaming $naming = new PlanNaming(),
    ) {}

    public function build(PlanRequest $request): PlanSet
    {
        $intents = $this->differ->diff($request->from, $request->to, $request->renames);
        $planner = $this->planners->get($request->driver);
        $table = $request->to->name;
        $timestamp = $request->timestamp();

        $safety = SafetyRunner::withDefaults($request->force)->run($intents, $request->database);

        $hasRename = $this->hasRename($intents);
        if (!$hasRename) {
            $migration = $this->makePlan($table, $intents, $planner, $timestamp, 0, '');

            return new PlanSet($table, [$migration], $safety);
        }

        [$phaseOne, $phaseTwo] = $this->splitForTwoPhase($intents, $request);

        $migrations = [
            $this->makePlan($table, $phaseOne, $planner, $timestamp, 0, 'phase1'),
            $this->makePlan($table, $phaseTwo, $planner, $timestamp, 1, 'phase2'),
        ];

        return new PlanSet($table, $migrations, $safety, twoPhase: true);
    }

    /**
     * @param list<IntentInterface> $intents
     */
    private function hasRename(array $intents): bool
    {
        foreach ($intents as $intent) {
            if ($intent instanceof RenameColumnIntent) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<IntentInterface> $intents
     *
     * @return array{list<IntentInterface>, list<IntentInterface>}
     */
    private function splitForTwoPhase(array $intents, PlanRequest $request): array
    {
        $quoter = IdentifierQuoter::forDriver($request->driver);
        $phaseOne = [];
        $phaseTwo = [];

        foreach ($intents as $intent) {
            if ($intent instanceof RenameColumnIntent) {
                $newColumn = $intent->column;
                $nullableCopy = $newColumn->asNullable();
                $phaseOne[] = new AddColumnIntent($intent->table, $nullableCopy);
                $phaseOne[] = new DataMigrationIntent(
                    $intent->table,
                    \sprintf(
                        'UPDATE %s SET %s = %s',
                        $quoter->quote($intent->table),
                        $quoter->quote($intent->to),
                        $quoter->quote($intent->from),
                    ),
                    \sprintf("copy '%s' into '%s'", $intent->from, $intent->to),
                );

                if (!$newColumn->nullable) {
                    $phaseTwo[] = new ChangeColumnIntent($intent->table, $nullableCopy, $newColumn);
                }

                $phaseTwo[] = new DropColumnIntent($intent->table, $this->oldColumn($request, $intent));

                continue;
            }

            if ($intent instanceof DropColumnIntent) {
                $phaseTwo[] = $intent;

                continue;
            }

            $phaseOne[] = $intent;
        }

        return [$phaseOne, $phaseTwo];
    }

    private function oldColumn(PlanRequest $request, RenameColumnIntent $intent): ColumnShape
    {
        return $request->from->column($intent->from) ?? $intent->column->withName($intent->from);
    }

    /**
     * @param list<IntentInterface> $intents
     */
    private function makePlan(
        string $table,
        array $intents,
        DialectPlanner $planner,
        int $timestamp,
        int $chunk,
        string $phase,
    ): MigrationPlan {
        $forward = [];
        foreach ($intents as $intent) {
            foreach ($planner->forward($intent) as $sql) {
                $forward[] = $sql;
            }
        }

        $rollback = [];
        foreach (array_reverse($intents) as $intent) {
            foreach ($planner->rollback($intent) as $sql) {
                $rollback[] = $sql;
            }
        }

        return new MigrationPlan(
            name: $this->naming->name($table, $phase),
            className: $this->naming->className($table, $timestamp, $phase),
            filename: $this->naming->path($table, $timestamp, $chunk, $phase),
            dialect: $planner->name(),
            operations: $intents,
            forwardSql: $forward,
            rollbackSql: $rollback,
            phase: $phase,
        );
    }
}
