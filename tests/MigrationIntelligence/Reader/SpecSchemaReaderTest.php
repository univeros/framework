<?php

declare(strict_types=1);

namespace Altair\Tests\MigrationIntelligence\Reader;

use Altair\MigrationIntelligence\Reader\SpecSchemaReader;
use Altair\MigrationIntelligence\Schema\ColumnType;
use Altair\Scaffold\Spec\Ast\PersistenceEntitySpec;
use Altair\Scaffold\Spec\Ast\PersistenceFieldSpec;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SpecSchemaReader::class)]
class SpecSchemaReaderTest extends TestCase
{
    public function testMapsFieldsToColumnsAndPromotesIntegerPrimary(): void
    {
        $entity = new PersistenceEntitySpec('App\\User', 'users', [
            new PersistenceFieldSpec('id', 'integer', primary: true),
            new PersistenceFieldSpec('email', 'string', unique: true),
            new PersistenceFieldSpec('name', 'string', nullable: true),
        ]);

        $shape = (new SpecSchemaReader())->fromEntity($entity);

        $this->assertSame('users', $shape->name);
        $this->assertSame(['id', 'email', 'name'], $shape->columnNames());

        $id = $shape->column('id');
        $this->assertNotNull($id);
        $this->assertSame(ColumnType::PRIMARY, $id->type);
        $this->assertTrue($id->primary);

        $name = $shape->column('name');
        $this->assertNotNull($name);
        $this->assertTrue($name->nullable);
    }

    public function testUniqueFieldBecomesUniqueIndex(): void
    {
        $entity = new PersistenceEntitySpec('App\\User', 'users', [
            new PersistenceFieldSpec('email', 'string', unique: true),
        ]);

        $shape = (new SpecSchemaReader())->fromEntity($entity);

        $this->assertCount(1, $shape->indexes);
        $this->assertSame(['email'], $shape->indexes[0]->columns);
        $this->assertTrue($shape->indexes[0]->unique);
    }

    public function testBigintPrimaryBecomesBigPrimary(): void
    {
        $entity = new PersistenceEntitySpec('App\\Order', 'orders', [
            new PersistenceFieldSpec('id', 'bigint', primary: true),
        ]);

        $shape = (new SpecSchemaReader())->fromEntity($entity);

        $id = $shape->column('id');
        $this->assertNotNull($id);
        $this->assertSame(ColumnType::BIG_PRIMARY, $id->type);
    }
}
