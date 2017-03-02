<?php
namespace Altair\Data\Contracts;


interface CreateRepositoryInterface
{
    /**
     * Create a new object and return it
     *
     * @param array $values
     *
     * @return EntityInterface
     */
    public function create(array $values): EntityInterface;
}
