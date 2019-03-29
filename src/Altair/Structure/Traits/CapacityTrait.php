<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Structure\Traits;

use Altair\Structure\Contracts\CapacityInterface;

/**
 * Common to structures that deal with an internal capacity.
 */
trait CapacityTrait
{
    /**
     * @var int internal capacity
     */
    protected $capacity = CapacityInterface::MIN_CAPACITY;

    /**
     * Returns the current capacity.
     *
     * @return int
     */
    public function capacity(): int
    {
        return $this->capacity;
    }

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
        $this->capacity = max($capacity, $this->capacity);

        return $this;
    }

    /**
     * Called when capacity should be increased to accommodate new values.
     */
    abstract protected function increaseCapacity();

    /**
     * Adjusts the structure's capacity according to its current size.
     */
    protected function adjustCapacity()
    {
        $size = count($this);

        // Automatically truncate the allocated buffer when the size of the
        // structure drops low enough.
        if ($size < $this->capacity / 4) {
            $this->capacity = max(CapacityInterface::MIN_CAPACITY, $this->capacity / 2);
        } else {
            // Also check if we should increase capacity when the size changes.
            if ($size >= $this->capacity) {
                $this->increaseCapacity();
            }
        }
    }
}
