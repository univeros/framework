<?php

declare(strict_types=1);

namespace Altair\Tests\MigrationIntelligence\Reader;

use Altair\MigrationIntelligence\Reader\EntitySchemaReader;
use Altair\MigrationIntelligence\Schema\ColumnType;
use Altair\Tests\MigrationIntelligence\Fixtures\SampleEntity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EntitySchemaReader::class)]
class EntitySchemaReaderTest extends TestCase
{
    public function testReadsTableAndColumnsFromCycleAttributes(): void
    {
        $shape = (new EntitySchemaReader())->read(SampleEntity::class);

        $this->assertNotNull($shape);
        $this->assertSame('widgets', $shape->name);
        $this->assertSame(['id', 'name', 'weight'], $shape->columnNames());

        $id = $shape->column('id');
        $this->assertNotNull($id);
        $this->assertTrue($id->primary);
        $this->assertSame(ColumnType::PRIMARY, $id->type);

        $name = $shape->column('name');
        $this->assertNotNull($name);
        $this->assertSame(ColumnType::STRING, $name->type);
        $this->assertSame(150, $name->size);
        $this->assertFalse($name->nullable);

        $weight = $shape->column('weight');
        $this->assertNotNull($weight);
        $this->assertSame(ColumnType::INTEGER, $weight->type);
        $this->assertTrue($weight->nullable);
    }

    public function testReturnsNullForNonEntityClass(): void
    {
        $this->assertNull((new EntitySchemaReader())->read(\stdClass::class));
    }

    public function testReturnsNullForMissingClass(): void
    {
        $this->assertNull((new EntitySchemaReader())->read('App\\Does\\Not\\Exist'));
    }
}
