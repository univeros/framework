<?php declare(strict_types=1);

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
     * @param $id
     *
     * @return EntityInterface|null
     */
    public function find($id): ?EntityInterface;

    /**
     * Finds all objects in the repository.
     *
     * @return array|null
     */
    public function findAll(): ?array;

    /**
     * Finds a single object by a set of criteria.
     *
     * @param array $criteria
     *
     * @return EntityInterface|null
     */
    public function findOneBy(array $criteria): ?EntityInterface;

    /**
     * @param array $condition
     *
     * @return array|null
     */
    public function findAllBy(array $condition): ?array;
}
