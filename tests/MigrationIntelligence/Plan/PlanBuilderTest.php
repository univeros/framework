<?php

declare(strict_types=1);

namespace Altair\Tests\MigrationIntelligence\Plan;

use Altair\MigrationIntelligence\Plan\PlanNaming;
use Altair\MigrationIntelligence\Plan\MigrationPlan;
use Altair\MigrationIntelligence\Plan\PlanSet;
use Altair\MigrationIntelligence\Intent\AddColumnIntent;
use Altair\MigrationIntelligence\Intent\DataMigrationIntent;
use Altair\MigrationIntelligence\Intent\DropColumnIntent;
use Altair\MigrationIntelligence\Plan\PlanBuilder;
use Altair\MigrationIntelligence\Plan\PlanRequest;
use Altair\MigrationIntelligence\Schema\ColumnShape;
use Altair\MigrationIntelligence\Schema\ColumnType;
use Altair\MigrationIntelligence\Schema\TableShape;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PlanBuilder::class)]
#[CoversClass(PlanNaming::class)]
#[CoversClass(MigrationPlan::class)]
#[CoversClass(PlanSet::class)]
#[CoversClass(PlanRequest::class)]
class PlanBuilderTest extends TestCase
{
    private const int FIXED_TS = 1_748_000_000;

    public function testSinglePhaseAddColumn(): void
    {
        $from = new TableShape('users', [new ColumnShape('id', ColumnType::PRIMARY, primary: true)]);
        $to = new TableShape('users', [
            new ColumnShape('id', ColumnType::PRIMARY, primary: true),
            new ColumnShape('display_name', ColumnType::STRING, nullable: true),
        ]);

        $plan = (new PlanBuilder())->build(new PlanRequest($from, $to, timestamp: self::FIXED_TS));

        $this->assertFalse($plan->twoPhase);
        $this->assertCount(1, $plan->migrations);
        $migration = $plan->migrations[0];
        $this->assertStringContainsString('AlterUsers', $migration->className);
        $this->assertSame(
            ['ALTER TABLE "users" ADD COLUMN "display_name" VARCHAR(255) NULL'],
            $migration->forwardSql,
        );
        $this->assertTrue($plan->safety->skipped);
        $this->assertSame(0, $plan->exitCode());
    }

    public function testTwoPhaseRenameExpandsIntoAddCopyAndDrop(): void
    {
        $from = new TableShape('users', [new ColumnShape('password', ColumnType::STRING, nullable: false)]);
        $to = new TableShape('users', [new ColumnShape('password_hash', ColumnType::STRING, nullable: false)]);

        $plan = (new PlanBuilder())->build(new PlanRequest(
            $from,
            $to,
            renames: ['password' => 'password_hash'],
            timestamp: self::FIXED_TS,
        ));

        $this->assertTrue($plan->twoPhase);
        $this->assertCount(2, $plan->migrations);

        [$phaseOne, $phaseTwo] = $plan->migrations;
        $this->assertSame('alter_users_phase1', $phaseOne->name);
        $this->assertInstanceOf(AddColumnIntent::class, $phaseOne->operations[0]);
        $this->assertInstanceOf(DataMigrationIntent::class, $phaseOne->operations[1]);
        $this->assertContains('UPDATE "users" SET "password_hash" = "password"', $phaseOne->forwardSql);

        $this->assertSame('alter_users_phase2', $phaseTwo->name);
        $kinds = array_map(static fn(object $intent): string => $intent::class, $phaseTwo->operations);
        $this->assertContains(DropColumnIntent::class, $kinds);
    }

    public function testNoChangesYieldEmptyPlan(): void
    {
        $shape = new TableShape('users', [new ColumnShape('id', ColumnType::PRIMARY, primary: true)]);

        $plan = (new PlanBuilder())->build(new PlanRequest($shape, $shape, timestamp: self::FIXED_TS));

        $this->assertTrue($plan->isEmpty());
    }

    public function testDistinctTimestampDrivenFilenamesAreDeterministic(): void
    {
        $from = new TableShape('orders', []);
        $to = new TableShape('orders', [new ColumnShape('note', ColumnType::TEXT, nullable: true)]);

        $plan = (new PlanBuilder())->build(new PlanRequest($from, $to, timestamp: self::FIXED_TS));

        $this->assertStringContainsString('database/migrations/', $plan->migrations[0]->filename);
        $this->assertStringEndsWith('_0_alter_orders.php', $plan->migrations[0]->filename);
    }
}
