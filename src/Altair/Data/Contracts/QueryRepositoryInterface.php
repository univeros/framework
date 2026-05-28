<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Data\Contracts;

interface QueryRepositoryInterface
{
    /**
     * Finds as single object by its identifier.
     *
     * @param int|string $id
     */
    public function find($id): ?EntityInterface;

    /**
     * Finds all objects in the repository.
     *
     * @return list<EntityInterface>|null
     */
    public function findAll(): ?array;

    /**
     * Finds a single object by a set of criteria.
     *
     * @param array<string, mixed> $criteria
     */
    public function findOneBy(array $criteria): ?EntityInterface;

    /**
     * @param array<string, mixed> $condition
     *
     * @return list<EntityInterface>|null
     */
    public function findAllBy(array $condition): ?array;
}
