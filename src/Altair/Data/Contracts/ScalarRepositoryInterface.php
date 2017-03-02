<?php
namespace Altair\Data\Contracts;

interface ScalarRepositoryInterface
{
    /**
     * Returns the value of a single field.
     *
     * @param mixed $id
     * @param string $field
     *
     * @return mixed
     */
    public function findScalar($id, string $field);

    /**
     * Returns the value of a single field for multiple entities.
     *
     * @param string $field
     *
     * @return array|null
     */
    public function findScalars(string $field): ?array;

    /**
     * Returns the value of a single field by variable criteria.
     *
     * @param array $criteria
     * @param string $field
     *
     * @return mixed
     */
    public function findScalarBy(array $criteria, string $field);
}
