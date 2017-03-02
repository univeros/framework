<?php
namespace Altair\Data\Contracts;

interface UpdateRepositoryInterface
{
    /**
     * Update an object on its storage and return the updated version.
     *
     * @param integer $id
     * @param array $values
     *
     * @return object
     */
    public function update($id, array $values);
}
