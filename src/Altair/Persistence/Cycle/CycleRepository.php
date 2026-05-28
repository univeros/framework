<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Persistence\Cycle;

use Altair\Persistence\Contracts\RepositoryInterface;
use Altair\Persistence\Contracts\UnitOfWorkInterface;
use Cycle\ORM\ORMInterface;
use Cycle\ORM\RepositoryInterface as CycleNativeRepository;
use Cycle\ORM\Select\Repository as CycleSelectRepository;
use Override;

/**
 * Generic repository adapter sitting on top of a Cycle ORM repository.
 *
 * Concrete repositories typically subclass this and pin the template:
 *
 *     final class UserRepository extends CycleRepository
 *     {
 *         public function __construct(ORMInterface $orm, UnitOfWorkInterface $uow)
 *         {
 *             parent::__construct(User::class, $orm, $uow);
 *         }
 *     }
 *
 * @template TEntity of object
 *
 * @implements RepositoryInterface<TEntity>
 */
class CycleRepository implements RepositoryInterface
{
    /** @var CycleNativeRepository<TEntity> */
    private readonly CycleNativeRepository $repository;

    /**
     * @param class-string<TEntity> $entityClass
     */
    public function __construct(
        private readonly string $entityClass,
        private readonly ORMInterface $orm,
        private readonly UnitOfWorkInterface $unitOfWork,
    ) {
        /** @var CycleNativeRepository<TEntity> $repository */
        $repository = $this->orm->getRepository($this->entityClass);
        $this->repository = $repository;
    }

    #[Override]
    public function find(int|string $id): ?object
    {
        /** @var TEntity|null $entity */
        $entity = $this->repository->findByPK($id);

        return $entity;
    }

    #[Override]
    public function findOneBy(array $criteria): ?object
    {
        /** @var TEntity|null $entity */
        $entity = $this->repository->findOne($criteria);

        return $entity;
    }

    #[Override]
    public function findBy(array $criteria): iterable
    {
        if ($this->repository instanceof CycleSelectRepository) {
            /** @var iterable<TEntity> $entities */
            $entities = $this->repository->findAll($criteria);

            return $entities;
        }

        /** @var iterable<TEntity> $entities */
        $entities = $this->repository->findAll();

        return $entities;
    }

    #[Override]
    public function findAll(): iterable
    {
        /** @var iterable<TEntity> $entities */
        $entities = $this->repository->findAll();

        return $entities;
    }

    #[Override]
    public function save(object $entity): void
    {
        $this->unitOfWork->persist($entity);
        $this->unitOfWork->flush();
    }

    #[Override]
    public function delete(object $entity): void
    {
        $this->unitOfWork->remove($entity);
        $this->unitOfWork->flush();
    }

    /**
     * @return class-string<TEntity>
     */
    public function entityClass(): string
    {
        return $this->entityClass;
    }
}
