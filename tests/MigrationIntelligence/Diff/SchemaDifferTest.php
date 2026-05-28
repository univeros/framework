<?php

declare(strict_types=1);

namespace Altair\Tests\MigrationIntelligence\Diff;

use Altair\MigrationIntelligence\Diff\SchemaDiffer;
use Altair\MigrationIntelligence\Intent\AddColumnIntent;
use Altair\MigrationIntelligence\Intent\AddIndexIntent;
use Altair\MigrationIntelligence\Intent\ChangeColumnIntent;
use Altair\MigrationIntelligence\Intent\DropColumnIntent;
use Altair\MigrationIntelligence\Intent\RenameColumnIntent;
use Altair\MigrationIntelligence\Schema\ColumnShape;
use Altair\MigrationIntelligence\Schema\ColumnType;
use Altair\MigrationIntelligence\Schema\IndexShape;
use Altair\MigrationIntelligence\Schema\TableShape;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SchemaDiffer::class)]
class SchemaDifferTest extends TestCase
{
    public function testDetectsAddedColumn(): void
    {
        $from = new TableShape('users', [new ColumnShape('id', ColumnType::PRIMARY, primary: true)]);
        $to = new TableShape('users', [
            new ColumnShape('id', ColumnType::PRIMARY, primary: true),
            new ColumnShape('email', ColumnType::STRING),
        ]);

        $intents = (new SchemaDiffer())->diff($from, $to);

        $this->assertCount(1, $intents);
        $this->assertInstanceOf(AddColumnIntent::class, $intents[0]);
        $this->assertSame('email', $intents[0]->column->name);
    }

    public function testDetectsDroppedColumn(): void
    {
        $from = new TableShape('users', [
            new ColumnShape('id', ColumnType::PRIMARY, primary: true),
            new ColumnShape('legacy', ColumnType::STRING),
        ]);
        $to = new TableShape('users', [new ColumnShape('id', ColumnType::PRIMARY, primary: true)]);

        $intents = (new SchemaDiffer())->diff($from, $to);

        $this->assertCount(1, $intents);
        $this->assertInstanceOf(DropColumnIntent::class, $intents[0]);
    }

    public function testDetectsChangedColumnAndFlagsIncompatibleTypeChange(): void
    {
        $from = new TableShape('users', [new ColumnShape('age', ColumnType::STRING)]);
        $to = new TableShape('users', [new ColumnShape('age', ColumnType::INTEGER)]);

        $intents = (new SchemaDiffer())->diff($from, $to);

        $this->assertCount(1, $intents);
        $change = $intents[0];
        $this->assertInstanceOf(ChangeColumnIntent::class, $change);
        $this->assertTrue($change->typeChanged());
        $this->assertTrue($change->incompatible);
    }

    public function testSafeWideningIsNotIncompatible(): void
    {
        $from = new TableShape('users', [new ColumnShape('id', ColumnType::INTEGER)]);
        $to = new TableShape('users', [new ColumnShape('id', ColumnType::BIG_INTEGER)]);

        $intents = (new SchemaDiffer())->diff($from, $to);

        $change = $intents[0];
        $this->assertInstanceOf(ChangeColumnIntent::class, $change);
        $this->assertFalse($change->incompatible);
    }

    public function testDeclaredRenameProducesRenameNotDropAndAdd(): void
    {
        $from = new TableShape('users', [new ColumnShape('password', ColumnType::STRING)]);
        $to = new TableShape('users', [new ColumnShape('password_hash', ColumnType::STRING)]);

        $intents = (new SchemaDiffer())->diff($from, $to, ['password' => 'password_hash']);

        $this->assertCount(1, $intents);
        $rename = $intents[0];
        $this->assertInstanceOf(RenameColumnIntent::class, $rename);
        $this->assertSame('password', $rename->from);
        $this->assertSame('password_hash', $rename->to);
    }

    public function testDetectsAddedUniqueIndex(): void
    {
        $from = new TableShape('users', [new ColumnShape('email', ColumnType::STRING)]);
        $to = new TableShape(
            'users',
            [new ColumnShape('email', ColumnType::STRING)],
            [new IndexShape(['email'], unique: true)],
        );

        $intents = (new SchemaDiffer())->diff($from, $to);

        $this->assertCount(1, $intents);
        $this->assertInstanceOf(AddIndexIntent::class, $intents[0]);
        $this->assertTrue($intents[0]->index->unique);
    }

    public function testNoChangesProduceNoIntents(): void
    {
        $shape = new TableShape('users', [new ColumnShape('id', ColumnType::PRIMARY, primary: true)]);

        $this->assertSame([], (new SchemaDiffer())->diff($shape, $shape));
    }
}
