<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Structure\Contracts;

interface SetInterface extends CollectionInterface
{
    /**
     * Adds zero or more values to the set.
     *
     * @param mixed ...$values
     *
     * @return SetInterface
     */
    public function add(...$values): SetInterface;

    /**
     * Ensures that enough memory is allocated for a specified capacity. This potentially reduces the number of
     * reallocations as the size increases.
     *
     * @param int $capacity The number of values for which capacity should be allocated. Capacity will stay the same if
     * this value is less than or equal to the current capacity.
     *
     * @return SetInterface
     */
    public function allocate(int $capacity): SetInterface;

    /**
     * Creates a new set using values from this set that aren't in another set.
     *
     * Formally: A \ B = {x ∈ A | x ∉ B}
     *
     * @param SetInterface $set
     *
     * @return SetInterface
     */
    public function diff(SetInterface $set): SetInterface;

    /**
     * Creates a new set using values in either this set or in another set,
     * but not in both.
     *
     * Formally: A ⊖ B = {x : x ∈ (A \ B) ∪ (B \ A)}
     *
     * @param SetInterface $set
     *
     * @return SetInterface
     */
    public function xor(SetInterface $set): SetInterface;

    /**
     * Returns the current capacity of the set.
     *
     * @return int
     */
    public function capacity(): int;

    /**
     * Determines whether the set contains all of zero or more values.
     *
     * @param mixed ...$values
     *
     * @return bool true if at least one value was provided and the set contains all given values, false otherwise.
     */
    public function contains(...$values): bool;

    /**
     * Returns a new set containing only the values for which a callback
     * returns true. A boolean test will be used if a callback is not provided.
     *
     * @param callable|null $callback Accepts a value, returns a boolean: true : include the value, false: skip the
     * value.
     *
     * @return SetInterface
     */
    public function filter(callable $callback = null): SetInterface;

    /**
     * Returns the first value in the set.
     *
     * @return mixed the first value in the set.
     */
    public function first();

    /**
     * Returns the last value in the set.
     *
     * @return mixed the last value in the set.
     */
    public function last();

    /**
     * Returns the value at a specified position in the set.
     *
     * @param int $position
     *
     * @throws \OutOfRangeException
     *
     * @return mixed|null
     *
     */
    public function get(int $position);

    /**
     * Creates a new set using values common to both this set and another set.
     *
     * In other words, returns a copy of this set with all values removed that
     * aren't in the other set.
     *
     * Formally: A ∩ B = {x : x ∈ A ∧ x ∈ B}
     *
     * @param SetInterface $set
     *
     * @return SetInterface
     */
    public function intersect(SetInterface $set): SetInterface;

    /**
     * Joins all values of the set into a string, adding an optional 'glue'
     * between them. Returns an empty string if the set is empty.
     *
     * @param string $glue
     *
     * @return string
     */
    public function join(string $glue = null): string;

    /**
     * Iteratively reduces the set to a single value using a callback.
     *
     * @param callable $callback Accepts the carry and current value, and returns an updated carry value.
     * @param mixed|null $initial Optional initial carry value.
     *
     * @return mixed The carry value of the final iteration, or the initial value if the set was empty.
     */
    public function reduce(callable $callback, $initial = null);

    /**
     * Removes zero or more values from the set.
     *
     * @param mixed ...$values
     */
    public function remove(...$values);

    /**
     * Returns a reversed copy of the set.
     *
     * @return SetInterface
     */
    public function reverse(): SetInterface;

    /**
     * Returns a subset of a given length starting at a specified offset.
     *
     * @param int $offset If the offset is non-negative, the set will start at that offset in the set. If offset is
     * negative, the set will start that far from the end.
     * @param int $length If a length is given and is positive, the resulting set will have up to that many values in
     * it. If the requested length results in an overflow, only values up to the end of the set will be included.
     * If a length is given and is negative, the set will stop that many values from the end. If a length is not
     * provided, the resulting set will contains all values between the offset and the end of the set.
     *
     * @return SetInterface
     */
    public function slice(int $offset, int $length = null): SetInterface;

    /**
     * Sorts the set in-place, based on an optional callable comparator.
     *
     * @param callable|null $comparator Accepts two values to be compared. Should return the result of a <=> b.
     *
     * @return SetInterface
     */
    public function sort(callable $comparator = null): SetInterface;

    /**
     * Returns the result of adding all given values to the set.
     *
     * @param array|\Traversable $values
     *
     * @return SetInterface
     */
    public function merge($values): SetInterface;

    /**
     * Returns the sum of all values in the set.
     *
     * @return int|float The sum of all the values in the set.
     */
    public function sum();

    /**
     * Creates a new set that contains the values of this set as well as the
     * values of another set.
     *
     * Formally: A ∪ B = {x: x ∈ A ∨ x ∈ B}
     *
     * @param SetInterface $set
     *
     * @return SetInterface
     */
    public function union(SetInterface $set): SetInterface;

    /**
     * Returns the MapInterface used internally to keep its values.
     *
     * @return MapInterface
     */
    public function getMap(): MapInterface;
}
