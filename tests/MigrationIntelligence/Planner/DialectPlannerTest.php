<?php

declare(strict_types=1);

namespace Altair\Tests\MigrationIntelligence\Planner;

use Altair\MigrationIntelligence\Intent\AddColumnIntent;
use Altair\MigrationIntelligence\Intent\AddForeignKeyIntent;
use Altair\MigrationIntelligence\Intent\AddIndexIntent;
use Altair\MigrationIntelligence\Intent\ChangeColumnIntent;
use Altair\MigrationIntelligence\Intent\DropColumnIntent;
use Altair\MigrationIntelligence\Intent\RenameColumnIntent;
use Altair\MigrationIntelligence\Planner\AbstractDialectPlanner;
use Altair\MigrationIntelligence\Planner\MySqlPlanner;
use Altair\MigrationIntelligence\Planner\PostgresPlanner;
use Altair\MigrationIntelligence\Planner\SqlitePlanner;
use Altair\MigrationIntelligence\Schema\ColumnShape;
use Altair\MigrationIntelligence\Schema\ColumnType;
use Altair\MigrationIntelligence\Schema\ForeignKeyShape;
use Altair\MigrationIntelligence\Schema\IndexShape;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AbstractDialectPlanner::class)]
#[CoversClass(PostgresPlanner::class)]
#[CoversClass(MySqlPlanner::class)]
#[CoversClass(SqlitePlanner::class)]
class DialectPlannerTest extends TestCase
{
    public function testPostgresAddColumn(): void
    {
        $intent = new AddColumnIntent('users', new ColumnShape('display_name', ColumnType::STRING, nullable: true));

        $this->assertSame(
            ['ALTER TABLE "users" ADD COLUMN "display_name" VARCHAR(255) NULL'],
            (new PostgresPlanner())->forward($intent),
        );
    }

    public function testMysqlAddColumnWithDefault(): void
    {
        $intent = new AddColumnIntent(
            'users',
            new ColumnShape('active', ColumnType::BOOLEAN, hasDefault: true, default: true),
        );

        $this->assertSame(
            ['ALTER TABLE `users` ADD COLUMN `active` TINYINT(1) NOT NULL DEFAULT 1'],
            (new MySqlPlanner())->forward($intent),
        );
    }

    public function testPostgresBooleanDefaultUsesKeyword(): void
    {
        $intent = new AddColumnIntent(
            'users',
            new ColumnShape('active', ColumnType::BOOLEAN, hasDefault: true, default: true),
        );

        $this->assertSame(
            ['ALTER TABLE "users" ADD COLUMN "active" BOOLEAN NOT NULL DEFAULT TRUE'],
            (new PostgresPlanner())->forward($intent),
        );
    }

    public function testRenameColumnIsConsistentAcrossDialects(): void
    {
        $intent = new RenameColumnIntent('users', 'password', 'password_hash', new ColumnShape('password_hash', ColumnType::STRING));

        $this->assertSame(
            ['ALTER TABLE "users" RENAME COLUMN "password" TO "password_hash"'],
            (new PostgresPlanner())->forward($intent),
        );
        $this->assertSame(
            ['ALTER TABLE `users` RENAME COLUMN `password` TO `password_hash`'],
            (new MySqlPlanner())->forward($intent),
        );
    }

    public function testPostgresSetNotNull(): void
    {
        $intent = new ChangeColumnIntent(
            'users',
            new ColumnShape('email', ColumnType::STRING, nullable: true),
            new ColumnShape('email', ColumnType::STRING, nullable: false),
        );

        $this->assertSame(
            ['ALTER TABLE "users" ALTER COLUMN "email" SET NOT NULL'],
            (new PostgresPlanner())->forward($intent),
        );
    }

    public function testMysqlChangeColumnUsesModify(): void
    {
        $intent = new ChangeColumnIntent(
            'users',
            new ColumnShape('email', ColumnType::STRING, nullable: true),
            new ColumnShape('email', ColumnType::STRING, nullable: false),
        );

        $this->assertSame(
            ['ALTER TABLE `users` MODIFY COLUMN `email` VARCHAR(255) NOT NULL'],
            (new MySqlPlanner())->forward($intent),
        );
    }

    public function testSqliteAlterColumnEmitsNote(): void
    {
        $intent = new ChangeColumnIntent(
            'users',
            new ColumnShape('age', ColumnType::STRING),
            new ColumnShape('age', ColumnType::INTEGER),
        );

        $sql = (new SqlitePlanner())->forward($intent);

        $this->assertCount(1, $sql);
        $this->assertStringStartsWith('-- sqlite:', $sql[0]);
    }

    public function testAddUniqueIndexAndItsRollback(): void
    {
        $intent = new AddIndexIntent('users', new IndexShape(['email'], unique: true));
        $planner = new PostgresPlanner();

        $this->assertSame(
            ['CREATE UNIQUE INDEX "users_email_unique" ON "users" ("email")'],
            $planner->forward($intent),
        );
        $this->assertSame(
            ['DROP INDEX "users_email_unique"'],
            $planner->rollback($intent),
        );
    }

    public function testMysqlDropIndexIncludesTable(): void
    {
        $intent = new AddIndexIntent('users', new IndexShape(['email'], unique: true));

        $this->assertSame(
            ['DROP INDEX `users_email_unique` ON `users`'],
            (new MySqlPlanner())->rollback($intent),
        );
    }

    public function testPostgresAddForeignKey(): void
    {
        $intent = new AddForeignKeyIntent('posts', new ForeignKeyShape(['user_id'], 'users', ['id'], onDelete: 'CASCADE'));

        $this->assertSame(
            ['ALTER TABLE "posts" ADD CONSTRAINT "posts_user_id_fk" FOREIGN KEY ("user_id") REFERENCES "users" ("id") ON DELETE CASCADE'],
            (new PostgresPlanner())->forward($intent),
        );
    }

    public function testDropColumnRollbackReAddsColumn(): void
    {
        $intent = new DropColumnIntent('users', new ColumnShape('legacy', ColumnType::STRING, nullable: true));

        $this->assertSame(
            ['ALTER TABLE "users" ADD COLUMN "legacy" VARCHAR(255) NULL'],
            (new PostgresPlanner())->rollback($intent),
        );
    }
}
