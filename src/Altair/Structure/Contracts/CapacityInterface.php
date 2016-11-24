<?php

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
