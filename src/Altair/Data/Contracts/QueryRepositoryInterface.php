<?php
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
