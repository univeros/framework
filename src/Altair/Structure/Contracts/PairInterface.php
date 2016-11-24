<?php

namespace Altair\Structure\Contracts;

interface PairInterface
{
    /**
     * Check if keys are equal.
     *
     * @param mixed $key
     *
     * @return bool
     */
    public function equalsKey($key): bool;

    /**
     * Returns a copy of the Pair.
     *
     * @return PairInterface
     */
    public function copy(): PairInterface;

    /**
     * Returns pair as array.
     *
     * @return array
     */
    public function toArray(): array;
}
