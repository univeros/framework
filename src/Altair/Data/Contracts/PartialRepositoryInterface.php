<?php
namespace Altair\Data\Contracts;

interface PartialRepositoryInterface
{
    /**
     * Returns a partially populated EntityInterface.
     *
     * @param int|string $id
     * @param array $fields
     *
     * @return EntityInterface|null
     */
    public function findPartial($id, array $fields): ?EntityInterface;

    /**
     * Returns a partially populated EntityInterface by a set of criteria.
     *
     * @param array $criteria
     * @param array $fields
     *
     * @return EntityInterface|null
     */
    public function findPartialBy(array $criteria, array $fields): ?EntityInterface;

    /**
     * Returns multiple partially populated EntityInterface[] by a set of criteria.
     *
     * @param array $criteria
     * @param array $fields
     *
     * @return array|null
     */
    public function findPartialsBy(array $criteria, array $fields): ?array;
}
