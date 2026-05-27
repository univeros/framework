<?php

declare(strict_types=1);

namespace Altair\Tests\Persistence\Cycle;

use Altair\Persistence\Contracts\RepositoryInterface;
use Altair\Persistence\Contracts\UnitOfWorkInterface;
use Altair\Persistence\Cycle\CycleEntityManager;
use Altair\Persistence\Cycle\CycleRepository;
use Altair\Tests\Persistence\Cycle\Fixture\Widget;
use Cycle\ORM\ORMInterface;
use Cycle\ORM\RepositoryInterface as CycleNativeRepository;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

final class CycleEntityManagerTest extends TestCase
{
    public function testReturnsGenericCycleRepositoryWhenNoBindingExists(): void
    {
        $orm = $this->createMock(ORMInterface::class);
        $orm->expects(self::once())
            ->method('getRepository')
            ->with(Widget::class)
            ->willReturn($this->createMock(CycleNativeRepository::class));

        $manager = new CycleEntityManager(
            orm: $orm,
            unitOfWork: $this->createMock(UnitOfWorkInterface::class),
            container: $this->createMock(ContainerInterface::class),
        );

        $repository = $manager->repository(Widget::class);

        self::assertInstanceOf(CycleRepository::class, $repository);
        self::assertSame(Widget::class, $repository->entityClass());
    }

    public function testResolvesBoundRepositoryThroughContainer(): void
    {
        $boundRepository = $this->createMock(RepositoryInterface::class);
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::once())
            ->method('get')
            ->with('App\\Widget\\WidgetRepository')
            ->willReturn($boundRepository);

        $manager = new CycleEntityManager(
            orm: $this->createMock(ORMInterface::class),
            unitOfWork: $this->createMock(UnitOfWorkInterface::class),
            container: $container,
            repositoryBindings: [Widget::class => 'App\\Widget\\WidgetRepository'],
        );

        self::assertSame($boundRepository, $manager->repository(Widget::class));
    }

    public function testUnitOfWorkExposed(): void
    {
        $uow = $this->createMock(UnitOfWorkInterface::class);
        $manager = new CycleEntityManager(
            orm: $this->createMock(ORMInterface::class),
            unitOfWork: $uow,
            container: $this->createMock(ContainerInterface::class),
        );

        self::assertSame($uow, $manager->unitOfWork());
    }
}
