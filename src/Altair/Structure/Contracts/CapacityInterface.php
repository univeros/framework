<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Structure\Contracts;

interface CapacityInterface
{
    const MIN_CAPACITY = 8;

    /**
     * Ensures that enough memory is allocated for a required capacity.
     *
     * @
     *
     * @param int $capacity The number of values for which capacity should be allocated. Capacity will stay the same if
     * this value is less than or equal to the current capacity.
     *
     * @return static
     */
    public function allocate(int $capacity);

    /**
     * Returns the current capacity of the sequence.
     *
     * @return int
     */
    public function capacity(): int;
}
