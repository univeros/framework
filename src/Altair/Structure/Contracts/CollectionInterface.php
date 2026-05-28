<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Structure\Contracts;

use Countable;
use JsonSerializable;
use Traversable;

/**
 * @template TKey
 * @template TValue
 *
 * @extends Traversable<TKey, TValue>
 */
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
     */
    public function isEmpty(): bool;

    /**
     * Returns an array representation of the collection.
     *
     * The format of the returned array is implementation-dependent.
     * Some implementations may throw an exception if an array representation
     * could not be created.
     *
     * Keys are narrowed to array-key because PHP arrays cannot hold arbitrary
     * key types even when the collection's TKey is unconstrained.
     *
     * @return array<array-key, TValue>
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
