<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Persistence\Contracts;

use Altair\Data\Contracts\DataObjectInterface;

/**
 * Top-level entry point for accessing repositories and the unit of work.
 *
 * @template TEntity of object
 */
interface EntityManagerInterface
{
    /**
     * Resolve a repository for the given entity class.
     *
     * Concrete implementations return either a domain-specific subclass
     * (when registered) or a generic adapter repository.
     *
     * @param class-string<TEntity> $entityClass
     *
     * @return RepositoryInterface<TEntity>
     */
    public function repository(string $entityClass): RepositoryInterface;

    /**
     * Resolve a read-only repository that projects the entity's rows into an
     * immutable Data object — the read side of the persistence layer.
     *
     * @template TDataObject of DataObjectInterface
     *
     * @param class-string<TEntity>     $entityClass
     * @param class-string<TDataObject> $dataObjectClass
     *
     * @return ReadModelRepositoryInterface<TDataObject>
     */
    public function readModel(string $entityClass, string $dataObjectClass): ReadModelRepositoryInterface;

    /**
     * Access the shared unit of work for batched writes.
     */
    public function unitOfWork(): UnitOfWorkInterface;
}
