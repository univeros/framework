<?php

namespace Altair\Structure\Contracts;

interface HashableInterface
{
    /**
     * Produces a scalar value to be used as the object's hash.
     *
     * @return mixed Scalar hash value.
     */
    public function hash();

    /**
     * Returns whether this object is considered equal to another.
     *
     * @param $obj
     *
     * @return bool true if equal, false otherwise.
     */
    public function equals($obj): bool;
}
