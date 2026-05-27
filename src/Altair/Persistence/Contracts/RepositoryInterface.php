<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Persistence\Contracts;

/**
 * Generic repository contract over a single entity type.
 *
 * Generics are expressed in PHPDoc so PHPStan can narrow return types
 * in concrete subclasses (e.g. `UserRepository extends CycleRepository<User>`).
 *
 * @template TEntity of object
 */
interface RepositoryInterface
{
    /**
     * Locate an entity by its primary key.
     *
     * @return TEntity|null
     */
    public function find(int|string $id): ?object;

    /**
     * Locate the first entity matching the given criteria.
     *
     * @param array<string, mixed> $criteria
     *
     * @return TEntity|null
     */
    public function findOneBy(array $criteria): ?object;

    /**
     * Iterate every entity matching the given criteria.
     *
     * @param array<string, mixed> $criteria
     *
     * @return iterable<TEntity>
     */
    public function findBy(array $criteria): iterable;

    /**
     * Iterate every entity in the underlying storage.
     *
     * @return iterable<TEntity>
     */
    public function findAll(): iterable;

    /**
     * Persist the entity (insert or update) and flush in a single call.
     *
     * For batched writes use the UnitOfWork directly.
     *
     * @param TEntity $entity
     */
    public function save(object $entity): void;

    /**
     * Delete the entity and flush in a single call.
     *
     * @param TEntity $entity
     */
    public function delete(object $entity): void;
}
