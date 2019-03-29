<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Structure\Traits;

/**
 * Common to structures that require a capacity which is a power of two.
 */
trait SquaredCapacityTrait
{
    use CapacityTrait;

    /**
     * Ensures that enough memory is allocated for a specified capacity. This potentially reduces the number of
     * reallocations as the size increases.
     *
     * @param int $capacity The number of values for which capacity should be allocated. Capacity will stay the same if
     * this value is less than or equal to the current capacity.
     *
     * @return $this
     */
    public function allocate(int $capacity)
    {
        $this->capacity = max($this->square($capacity), $this->capacity);

        return $this;
    }

    /**
     * Called when capacity should be increased to accommodate new values.
     *
     * @return $this
     */
    protected function increaseCapacity()
    {
        $this->capacity = $this->square(max(count($this), $this->capacity + 1));

        return $this;
    }

    /**
     * Rounds an integer to the next power of two if not already a power of two.
     *
     * @param int $capacity
     *
     * @return int
     */
    private function square(int $capacity): int
    {
        return pow(2, ceil(log($capacity, 2)));
    }
}
