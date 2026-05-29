<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Tests\Persistence\Cycle;

use Altair\Persistence\Configuration\DatabaseConnectionFactory;
use Altair\Persistence\Configuration\DatabaseSettings;
use Altair\Persistence\Cycle\CycleReadModelRepository;
use Altair\Persistence\Dto\DataObjectHydrator;
use Altair\Tests\Persistence\Cycle\Fixture\Part;
use Altair\Tests\Persistence\Cycle\Fixture\PartDto;
use Altair\Tests\Persistence\Cycle\Fixture\Widget;
use Altair\Tests\Persistence\Cycle\Fixture\WidgetWithPartsDto;
use Cycle\ORM\Factory;
use Cycle\ORM\Mapper\Mapper;
use Cycle\ORM\ORM;
use Cycle\ORM\ORMInterface;
use Cycle\ORM\Relation;
use Cycle\ORM\Schema;
use Cycle\ORM\SchemaInterface;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CycleReadModelRepository::class)]
final class CycleReadModelRelationTest extends TestCase
{
    private ORMInterface $orm;

    #[Override]
    protected function setUp(): void
    {
        $databases = (new DatabaseConnectionFactory())->create(new DatabaseSettings(
            driver: DatabaseSettings::DRIVER_SQLITE,
            database: ':memory:',
        ));

        $db = $databases->database('default');
        $db->execute('CREATE TABLE widgets (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');
        $db->execute('CREATE TABLE parts (id INTEGER PRIMARY KEY, widget_id INTEGER NOT NULL, label TEXT NOT NULL)');
        $db->execute('INSERT INTO widgets (id, name) VALUES (1, ?), (2, ?)', ['Alpha', 'Bravo']);
        $db->execute('INSERT INTO parts (id, widget_id, label) VALUES (1, 1, ?), (2, 1, ?)', ['p1', 'p2']);

        $this->orm = new ORM(new Factory($databases), $this->schema());
    }

    private function readModel(): CycleReadModelRepository
    {
        return new CycleReadModelRepository(
            Widget::class,
            WidgetWithPartsDto::class,
            $this->orm,
            new DataObjectHydrator(),
        );
    }

    public function testFindEagerLoadsAndNestsHasManyRelation(): void
    {
        $dto = $this->readModel()->find(1);

        $this->assertInstanceOf(WidgetWithPartsDto::class, $dto);
        $this->assertSame('Alpha', $dto->name);
        $this->assertIsArray($dto->parts);
        $this->assertCount(2, $dto->parts);
        $this->assertContainsOnlyInstancesOf(PartDto::class, $dto->parts);

        $labels = array_map(static fn(PartDto $part): ?string => $part->label, $dto->parts);
        sort($labels);
        $this->assertSame(['p1', 'p2'], $labels);
    }

    public function testRelationIsEmptyListWhenNoChildren(): void
    {
        $dto = $this->readModel()->find(2);

        $this->assertInstanceOf(WidgetWithPartsDto::class, $dto);
        $this->assertSame([], $dto->parts);
    }

    public function testFindAllEagerLoadsRelationForEveryRow(): void
    {
        $dtos = $this->readModel()->findAll();

        $this->assertCount(2, $dtos);
        $byId = [];
        foreach ($dtos as $dto) {
            $byId[$dto->id] = $dto;
        }

        $this->assertCount(2, $byId[1]->parts);
        $this->assertSame([], $byId[2]->parts);
    }

    private function schema(): Schema
    {
        return new Schema([
            'widget' => [
                SchemaInterface::ENTITY => Widget::class,
                SchemaInterface::MAPPER => Mapper::class,
                SchemaInterface::DATABASE => 'default',
                SchemaInterface::TABLE => 'widgets',
                SchemaInterface::PRIMARY_KEY => ['id'],
                SchemaInterface::COLUMNS => ['id' => 'id', 'name' => 'name'],
                SchemaInterface::TYPECAST => ['id' => 'int'],
                SchemaInterface::SCHEMA => [],
                SchemaInterface::RELATIONS => [
                    'parts' => [
                        Relation::TYPE => Relation::HAS_MANY,
                        Relation::TARGET => 'part',
                        Relation::LOAD => Relation::LOAD_PROMISE,
                        Relation::SCHEMA => [
                            Relation::CASCADE => true,
                            Relation::INNER_KEY => 'id',
                            Relation::OUTER_KEY => 'widget_id',
                        ],
                    ],
                ],
            ],
            'part' => [
                SchemaInterface::ENTITY => Part::class,
                SchemaInterface::MAPPER => Mapper::class,
                SchemaInterface::DATABASE => 'default',
                SchemaInterface::TABLE => 'parts',
                SchemaInterface::PRIMARY_KEY => ['id'],
                SchemaInterface::COLUMNS => ['id' => 'id', 'widget_id' => 'widget_id', 'label' => 'label'],
                SchemaInterface::TYPECAST => ['id' => 'int', 'widget_id' => 'int'],
                SchemaInterface::SCHEMA => [],
                SchemaInterface::RELATIONS => [],
            ],
        ]);
    }
}
