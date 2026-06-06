<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Tests\Persistence\Cycle;

use Altair\Persistence\Cycle\CycleEntityManager;
use Altair\Persistence\Cycle\CycleReadModelRepository;
use Altair\Persistence\Cycle\CycleRepository;
use Altair\Persistence\Cycle\CycleUnitOfWork;
use Altair\Persistence\Dto\DataObjectHydrator;
use Altair\Tests\Persistence\Cycle\Fixture\Widget;
use Altair\Tests\Persistence\Cycle\Fixture\WidgetDto;
use Cycle\Database\DatabaseManager;
use Cycle\ORM\Factory;
use Cycle\ORM\Mapper\Mapper;
use Cycle\ORM\ORM;
use Cycle\ORM\ORMInterface;
use Cycle\ORM\Schema;
use Cycle\ORM\SchemaInterface;
use Altair\Persistence\Configuration\DatabaseConnectionFactory;
use Altair\Persistence\Configuration\DatabaseSettings;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

#[CoversClass(CycleReadModelRepository::class)]
final class CycleReadModelRepositoryTest extends TestCase
{
    private DatabaseManager $databases;

    private ORMInterface $orm;

    private CycleUnitOfWork $unitOfWork;

    #[Override]
    protected function setUp(): void
    {
        $this->databases = (new DatabaseConnectionFactory())->create(new DatabaseSettings(
            driver: DatabaseSettings::DRIVER_SQLITE,
            database: ':memory:',
        ));

        $this->databases->database('default')->execute(
            'CREATE TABLE widgets (id INTEGER PRIMARY KEY, name TEXT NOT NULL)'
        );

        $this->orm = new ORM(new Factory($this->databases), new Schema([
            'widget' => [
                SchemaInterface::ENTITY => Widget::class,
                SchemaInterface::MAPPER => Mapper::class,
                SchemaInterface::DATABASE => 'default',
                SchemaInterface::TABLE => 'widgets',
                SchemaInterface::PRIMARY_KEY => ['id'],
                SchemaInterface::COLUMNS => ['id' => 'id', 'name' => 'name'],
                SchemaInterface::TYPECAST => ['id' => 'int'],
                SchemaInterface::SCHEMA => [],
                SchemaInterface::RELATIONS => [],
            ],
        ]));

        $this->unitOfWork = new CycleUnitOfWork($this->orm);

        $writes = new CycleRepository(Widget::class, $this->orm, $this->unitOfWork);
        $writes->save(new Widget(id: 1, name: 'Alpha'));
        $writes->save(new Widget(id: 2, name: 'Bravo'));
    }

    private function readModel(): CycleReadModelRepository
    {
        return new CycleReadModelRepository(Widget::class, WidgetDto::class, $this->orm, new DataObjectHydrator());
    }

    public function testFindReturnsHydratedDataObject(): void
    {
        $dto = $this->readModel()->find(1);

        $this->assertInstanceOf(WidgetDto::class, $dto);
        $this->assertSame(1, $dto->id);
        $this->assertSame('Alpha', $dto->name);
    }

    public function testFindReturnsNullWhenMissing(): void
    {
        $this->assertNull($this->readModel()->find(999));
    }

    public function testFindOneByCriteria(): void
    {
        $dto = $this->readModel()->findOneBy(['name' => 'Bravo']);

        $this->assertInstanceOf(WidgetDto::class, $dto);
        $this->assertSame(2, $dto->id);
    }

    public function testFindAllReturnsEveryRowAsDataObject(): void
    {
        $dtos = $this->readModel()->findAll();

        $this->assertCount(2, $dtos);
        $this->assertContainsOnlyInstancesOf(WidgetDto::class, $dtos);

        $names = array_map(static fn(WidgetDto $dto): ?string => $dto->name, $dtos);
        sort($names);
        $this->assertSame(['Alpha', 'Bravo'], $names);
    }

    public function testFindByCriteria(): void
    {
        $dtos = $this->readModel()->findBy(['name' => 'Alpha']);

        $this->assertCount(1, $dtos);
        $this->assertSame(1, $dtos[0]->id);
    }

    public function testReadModelObtainedThroughEntityManager(): void
    {
        $manager = new CycleEntityManager(
            orm: $this->orm,
            unitOfWork: $this->unitOfWork,
            container: $this->createStub(ContainerInterface::class),
            hydrator: new DataObjectHydrator(),
        );

        $dto = $manager->readModel(Widget::class, WidgetDto::class)->find(2);

        $this->assertInstanceOf(WidgetDto::class, $dto);
        $this->assertSame('Bravo', $dto->name);
    }
}
