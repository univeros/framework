<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

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
