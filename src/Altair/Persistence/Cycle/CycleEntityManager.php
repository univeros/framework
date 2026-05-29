<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Persistence\Cycle;

use Altair\Data\Contracts\DataObjectInterface;
use Altair\Persistence\Contracts\EntityManagerInterface;
use Altair\Persistence\Contracts\HydratorInterface;
use Altair\Persistence\Contracts\ReadModelRepositoryInterface;
use Altair\Persistence\Contracts\RepositoryInterface;
use Altair\Persistence\Contracts\UnitOfWorkInterface;
use Altair\Persistence\Dto\DataObjectHydrator;
use Cycle\ORM\ORMInterface;
use Override;
use Psr\Container\ContainerInterface;

/**
 * Top-level facade for Cycle-backed persistence.
 *
 * Resolves domain-specific repositories from the application container when
 * registered (e.g. `UserRepository`), otherwise hands back a generic
 * {@see CycleRepository}.
 *
 * @template TEntity of object
 *
 * @implements EntityManagerInterface<TEntity>
 */
final readonly class CycleEntityManager implements EntityManagerInterface
{
    /**
     * @param array<class-string, class-string<RepositoryInterface<object>>> $repositoryBindings
     *        Entity class => repository class. The repository class must be
     *        resolvable through the container.
     */
    public function __construct(
        private ORMInterface $orm,
        private UnitOfWorkInterface $unitOfWork,
        private ContainerInterface $container,
        private ?HydratorInterface $hydrator = null,
        private array $repositoryBindings = [],
    ) {}

    #[Override]
    public function repository(string $entityClass): RepositoryInterface
    {
        if (isset($this->repositoryBindings[$entityClass])) {
            /** @var RepositoryInterface<TEntity> $repository */
            $repository = $this->container->get($this->repositoryBindings[$entityClass]);

            return $repository;
        }

        /** @var class-string<TEntity> $entityClass */
        return new CycleRepository($entityClass, $this->orm, $this->unitOfWork);
    }

    /**
     * @template TDataObject of DataObjectInterface
     *
     * @param class-string<TEntity>     $entityClass
     * @param class-string<TDataObject> $dataObjectClass
     *
     * @return ReadModelRepositoryInterface<TDataObject>
     */
    #[Override]
    public function readModel(string $entityClass, string $dataObjectClass): ReadModelRepositoryInterface
    {
        return new CycleReadModelRepository(
            $entityClass,
            $dataObjectClass,
            $this->orm,
            $this->hydrator ?? new DataObjectHydrator(),
        );
    }

    #[Override]
    public function unitOfWork(): UnitOfWorkInterface
    {
        return $this->unitOfWork;
    }
}
