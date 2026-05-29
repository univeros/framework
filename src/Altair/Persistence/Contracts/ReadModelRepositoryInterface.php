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
 * Read-only repository that returns immutable Data objects rather than managed
 * entities — the read side of the persistence layer.
 *
 * Writes are not part of this contract: they go through the entity
 * {@see RepositoryInterface} and {@see UnitOfWorkInterface}. A read model
 * projects storage rows into {@see DataObjectInterface} read models via a
 * {@see HydratorInterface}.
 *
 * @template TDataObject of DataObjectInterface
 */
interface ReadModelRepositoryInterface
{
    /**
     * Locate a single read model by primary key.
     *
     * @return TDataObject|null
     */
    public function find(int|string $id): ?DataObjectInterface;

    /**
     * Locate the first read model matching the given criteria.
     *
     * @param array<string, mixed> $criteria
     *
     * @return TDataObject|null
     */
    public function findOneBy(array $criteria): ?DataObjectInterface;

    /**
     * Locate every read model matching the given criteria.
     *
     * @param array<string, mixed> $criteria
     *
     * @return list<TDataObject>
     */
    public function findBy(array $criteria): array;

    /**
     * Locate every read model in the underlying storage.
     *
     * @return list<TDataObject>
     */
    public function findAll(): array;
}
