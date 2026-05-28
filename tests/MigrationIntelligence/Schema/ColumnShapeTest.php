<?php

declare(strict_types=1);

namespace Altair\Tests\MigrationIntelligence\Schema;

use Altair\MigrationIntelligence\Schema\ColumnShape;
use Altair\MigrationIntelligence\Schema\ColumnType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ColumnShape::class)]
class ColumnShapeTest extends TestCase
{
    public function testWithNameReturnsACopyKeepingDefinition(): void
    {
        $column = new ColumnShape('old', ColumnType::STRING, nullable: true, size: 120);

        $renamed = $column->withName('new');

        $this->assertSame('new', $renamed->name);
        $this->assertSame('old', $column->name);
        $this->assertSame(ColumnType::STRING, $renamed->type);
        $this->assertTrue($renamed->nullable);
        $this->assertSame(120, $renamed->size);
    }

    public function testSameTypeComparesTypeAndSize(): void
    {
        $a = new ColumnShape('a', ColumnType::STRING, size: 255);
        $b = new ColumnShape('b', ColumnType::STRING, size: 255);
        $c = new ColumnShape('c', ColumnType::STRING, size: 120);

        $this->assertTrue($a->sameTypeAs($b));
        $this->assertFalse($a->sameTypeAs($c));
    }

    public function testDefinitionDiffersOnNullabilityChange(): void
    {
        $before = new ColumnShape('email', ColumnType::STRING, nullable: true);
        $after = new ColumnShape('email', ColumnType::STRING, nullable: false);

        $this->assertTrue($after->definitionDiffersFrom($before));
    }

    public function testDefinitionDiffersOnDefaultChange(): void
    {
        $before = new ColumnShape('role', ColumnType::STRING, hasDefault: true, default: 'user');
        $after = new ColumnShape('role', ColumnType::STRING, hasDefault: true, default: 'member');

        $this->assertTrue($after->definitionDiffersFrom($before));
    }

    public function testIdenticalDefinitionsDoNotDiffer(): void
    {
        $before = new ColumnShape('email', ColumnType::STRING, nullable: false, size: 255);
        $after = new ColumnShape('email', ColumnType::STRING, nullable: false, size: 255);

        $this->assertFalse($after->definitionDiffersFrom($before));
    }
}
