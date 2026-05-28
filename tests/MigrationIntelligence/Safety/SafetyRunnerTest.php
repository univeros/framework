<?php

declare(strict_types=1);

namespace Altair\Tests\MigrationIntelligence\Safety;

use Altair\MigrationIntelligence\Safety\Check\NotNullSafetyCheck;
use Altair\MigrationIntelligence\Safety\Check\UniqueSafetyCheck;
use Altair\MigrationIntelligence\Safety\Check\ForeignKeySafetyCheck;
use Altair\MigrationIntelligence\Safety\Check\DropColumnSafetyCheck;
use Altair\MigrationIntelligence\Safety\Check\TypeCastSafetyCheck;
use Altair\MigrationIntelligence\Safety\RowCounter;
use Altair\MigrationIntelligence\Safety\SafetyReport;
use Altair\MigrationIntelligence\Safety\IdentifierQuoter;
use Altair\MigrationIntelligence\Intent\AddColumnIntent;
use Altair\MigrationIntelligence\Intent\AddForeignKeyIntent;
use Altair\MigrationIntelligence\Intent\AddIndexIntent;
use Altair\MigrationIntelligence\Intent\ChangeColumnIntent;
use Altair\MigrationIntelligence\Intent\DropColumnIntent;
use Altair\MigrationIntelligence\Safety\Check\LargeTableSafetyCheck;
use Altair\MigrationIntelligence\Safety\SafetyRunner;
use Altair\MigrationIntelligence\Schema\ColumnShape;
use Altair\MigrationIntelligence\Schema\ColumnType;
use Altair\MigrationIntelligence\Schema\ForeignKeyShape;
use Altair\MigrationIntelligence\Schema\IndexShape;
use Altair\Tests\MigrationIntelligence\Support\SqliteDatabaseFactory;
use Cycle\Database\DatabaseInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SafetyRunner::class)]
#[CoversClass(NotNullSafetyCheck::class)]
#[CoversClass(UniqueSafetyCheck::class)]
#[CoversClass(ForeignKeySafetyCheck::class)]
#[CoversClass(DropColumnSafetyCheck::class)]
#[CoversClass(TypeCastSafetyCheck::class)]
#[CoversClass(LargeTableSafetyCheck::class)]
#[CoversClass(RowCounter::class)]
#[CoversClass(SafetyReport::class)]
#[CoversClass(IdentifierQuoter::class)]
class SafetyRunnerTest extends TestCase
{
    public function testSkippedWhenNoDatabase(): void
    {
        $report = SafetyRunner::withDefaults()->run([], null);

        $this->assertTrue($report->skipped);
        $this->assertNotNull($report->skipReason);
    }

    public function testNotNullWithoutDefaultOnPopulatedTableIsError(): void
    {
        $database = $this->seedUsers();
        $intent = new AddColumnIntent('users', new ColumnShape('display_name', ColumnType::STRING, nullable: false));

        $report = SafetyRunner::withDefaults()->run([$intent], $database);

        $this->assertTrue($report->hasErrors());
        $this->assertStringContainsString('NOT NULL', $report->errors()[0]->message);
    }

    public function testNullableColumnAddIsSafe(): void
    {
        $database = $this->seedUsers();
        $intent = new AddColumnIntent('users', new ColumnShape('display_name', ColumnType::STRING, nullable: true));

        $report = SafetyRunner::withDefaults()->run([$intent], $database);

        $this->assertFalse($report->hasErrors());
    }

    public function testUniqueIndexWithDuplicatesIsError(): void
    {
        $database = $this->seedUsers();
        // role column has duplicate 'member' values.
        $intent = new AddIndexIntent('users', new IndexShape(['role'], unique: true));

        $report = SafetyRunner::withDefaults()->run([$intent], $database);

        $this->assertTrue($report->hasErrors());
        $this->assertSame('unique', $report->errors()[0]->check);
    }

    public function testForeignKeyWithOrphansIsError(): void
    {
        $database = $this->seedUsers();
        $database->execute('CREATE TABLE posts (id INTEGER PRIMARY KEY, user_id INTEGER)');
        $database->execute('INSERT INTO posts (user_id) VALUES (1), (999)');

        $intent = new AddForeignKeyIntent('posts', new ForeignKeyShape(['user_id'], 'users', ['id']));

        $report = SafetyRunner::withDefaults()->run([$intent], $database);

        $this->assertTrue($report->hasErrors());
        $this->assertSame('foreign_key', $report->errors()[0]->check);
    }

    public function testDropColumnWithDataIsErrorUnlessForced(): void
    {
        $database = $this->seedUsers();
        $intent = new DropColumnIntent('users', new ColumnShape('email', ColumnType::STRING));

        $blocked = SafetyRunner::withDefaults(force: false)->run([$intent], $database);
        $this->assertTrue($blocked->hasErrors());

        $forced = SafetyRunner::withDefaults(force: true)->run([$intent], $database);
        $this->assertFalse($forced->hasErrors());
        $this->assertTrue($forced->hasWarnings());
    }

    public function testIncompatibleTypeChangeWithNonCastableDataIsError(): void
    {
        $database = $this->seedUsers();
        $intent = new ChangeColumnIntent(
            'users',
            new ColumnShape('email', ColumnType::STRING),
            new ColumnShape('email', ColumnType::INTEGER),
            incompatible: true,
        );

        $report = SafetyRunner::withDefaults()->run([$intent], $database);

        $this->assertTrue($report->hasErrors());
        $this->assertSame('type_cast', $report->errors()[0]->check);
    }

    public function testLargeTableWarnsAboveThreshold(): void
    {
        $database = $this->seedUsers();
        $runner = new SafetyRunner(new LargeTableSafetyCheck(threshold: 2));
        $intent = new AddColumnIntent('users', new ColumnShape('flag', ColumnType::BOOLEAN, nullable: true));

        $report = $runner->run([$intent], $database);

        $this->assertTrue($report->hasWarnings());
    }

    private function seedUsers(): DatabaseInterface
    {
        $database = SqliteDatabaseFactory::memory();
        $database->execute('CREATE TABLE users (id INTEGER PRIMARY KEY, email VARCHAR, role VARCHAR)');
        $database->execute(
            'INSERT INTO users (email, role) VALUES ("a@x.test", "member"), ("b@x.test", "member"), ("c@x.test", "admin")',
        );

        return $database;
    }
}
