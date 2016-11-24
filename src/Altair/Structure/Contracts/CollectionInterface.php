<?php

namespace Altair\Structure\Contracts;

use Countable;
use JsonSerializable;
use Traversable;

interface CollectionInterface extends Traversable, Countable, JsonSerializable
{
    /**
     * Removes all values from the collection.
     *
     * @return static
     */
    public function clear();

    /**
     * Returns the size of the collection.
     *
     * @return int
     */
    public function count(): int;

    /**
     * Returns a shallow copy of the collection.
     *
     * @return static
     */
    public function copy();

    /**
     * Returns whether the collection is empty.
     *
     * This should be equivalent to a count of zero, but is not required.
     * Implementations should define what empty means in their own context.
     *
     * @return bool
     */
    public function isEmpty(): bool;

    /**
     * Returns an array representation of the collection.
     *
     * The format of the returned array is implementation-dependent.
     * Some implementations may throw an exception if an array representation
     * could not be created.
     *
     * @return array
     */
    public function toArray(): array;

    /**
     * Returns the collection of items as JSON.
     *
     * @param int $options Bitmask of the different options of the json_encode function.
     * @param int $depth Sets the maximum depth. Must be greater than 0.
     *
     * @return string a JSON encoded string of the items or false on failure
     */
    public function toJson(int $options = 0, $depth = 512): string;
}
