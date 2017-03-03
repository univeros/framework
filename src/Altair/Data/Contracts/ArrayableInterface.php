<?php
namespace Altair\Data\Contracts;

interface ArrayableInterface
{
    /**
     * Gets the instance of the object as an array.
     *
     * @return array
     */
    public function toArray(): array;
}
