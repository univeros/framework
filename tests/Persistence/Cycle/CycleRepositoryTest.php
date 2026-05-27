<?php

declare(strict_types=1);

namespace Altair\Tests\Persistence\Cycle;

use Cycle\ORM\Mapper\Mapper;
use Altair\Persistence\Configuration\DatabaseConnectionFactory;
use Altair\Persistence\Configuration\DatabaseSettings;
use Altair\Persistence\Cycle\CycleRepository;
use Altair\Persistence\Cycle\CycleUnitOfWork;
use Altair\Tests\Persistence\Cycle\Fixture\Widget;
use Cycle\Database\DatabaseManager;
use Cycle\ORM\Factory;
use Cycle\ORM\ORM;
use Cycle\ORM\ORMInterface;
use Cycle\ORM\Schema;
use Cycle\ORM\SchemaInterface;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end test of {@see CycleRepository} round-trips against an in-memory
 * SQLite database. Verifies that the abstraction does not need
 * Cycle-specific types to leak into call sites.
 */
final class CycleRepositoryTest extends TestCase
{
    private DatabaseManager $databases;

    private ORMInterface $orm;

    private CycleUnitOfWork $unitOfWork;

    #[\Override]
    protected function setUp(): void
    {
        $this->databases = (new DatabaseConnectionFactory())->create(new DatabaseSettings(
            driver: DatabaseSettings::DRIVER_SQLITE,
            database: ':memory:',
        ));

        $this->createWidgetsTable();

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
    }

    public function testSavePersistsEntityAndFindReturnsIt(): void
    {
        $repository = new CycleRepository(Widget::class, $this->orm, $this->unitOfWork);

        $repository->save(new Widget(id: 1, name: 'Alpha'));

        $found = $repository->find(1);
        self::assertNotNull($found);
        self::assertSame('Alpha', $found->name);
    }

    public function testFindAllReturnsEveryRow(): void
    {
        $repository = new CycleRepository(Widget::class, $this->orm, $this->unitOfWork);

        $repository->save(new Widget(id: 1, name: 'Alpha'));
        $repository->save(new Widget(id: 2, name: 'Bravo'));

        $names = [];
        foreach ($repository->findAll() as $widget) {
            $names[] = $widget->name;
        }

        sort($names);

        self::assertSame(['Alpha', 'Bravo'], $names);
    }

    public function testDeleteRemovesEntity(): void
    {
        $repository = new CycleRepository(Widget::class, $this->orm, $this->unitOfWork);

        $widget = new Widget(id: 1, name: 'Alpha');
        $repository->save($widget);

        $repository->delete($widget);

        self::assertNull($repository->find(1));
    }

    public function testEntityClassExposed(): void
    {
        $repository = new CycleRepository(Widget::class, $this->orm, $this->unitOfWork);

        self::assertSame(Widget::class, $repository->entityClass());
    }

    private function createWidgetsTable(): void
    {
        $this->databases->database('default')->execute(
            'CREATE TABLE widgets (id INTEGER PRIMARY KEY, name TEXT NOT NULL)'
        );
    }
}
