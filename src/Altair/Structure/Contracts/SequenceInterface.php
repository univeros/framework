<?php

namespace Altair\Structure\Contracts;

interface SequenceInterface extends CollectionInterface
{
    /**
     * Creates a new sequence using the values of either an array or iterable object as initial values.
     *
     * @param array|\Traversable|null $values
     */
    public function __construct($values = null);

    /**
     * Updates every value in the sequence by applying a callback, using the return value as the new value.
     *
     * @param callable $callback Accepts the value, returns the new value.
     *
     * @return SequenceInterface
     */
    public function apply(callable $callback): SequenceInterface;

    /**
     * Determines whether the sequence contains all of zero or more values.
     *
     * @param mixed ...$values
     *
     * @return bool true if at least one value was provided and the sequence contains all given values, false otherwise.
     */
    public function contains(...$values): bool;

    /**
     * Returns a new sequence containing only the values for which a callback
     * returns true. A boolean test will be used if a callback is not provided.
     *
     * @param callable|null $callback Accepts a value, returns a boolean result: true : include the value, false: skip
     * the value.
     *
     * @return SequenceInterface
     */
    public function filter(callable $callback = null): SequenceInterface;

    /**
     * Returns the index of a given value, or false if it could not be found.
     *
     * @param mixed $value
     *
     * @return int|bool
     */
    public function find($value);

    /**
     * Returns the first value in the sequence.
     *
     * @throws \UnderflowException if the sequence is empty.
     *
     * @return mixed
     *
     */
    public function first();

    /**
     * Returns the value at a given index (position) in the sequence.
     *
     * @param int $index
     *
     * @throws \OutOfRangeException if the index is not in the range [0, size-1]
     *
     * @return mixed
     *
     */
    public function get(int $index);

    /**
     * Inserts zero or more values at a given index.
     *
     * Each value after the index will be moved one position to the right.
     * Values may be inserted at an index equal to the size of the sequence.
     *
     * @param int $index
     * @param mixed ...$values
     *
     * @throws \OutOfRangeException if the index is not in the range [0, n]
     *
     * @return SequenceInterface
     */
    public function insert(int $index, ...$values): SequenceInterface;

    /**
     * Joins all values of the sequence into a string, adding an optional 'glue' between them. Returns an empty string
     * if the sequence is empty.
     *
     * @param string $glue
     *
     * @return string
     */
    public function join(string $glue = null): string;

    /**
     * Returns the last value in the sequence.
     *
     * @throws \UnderflowException if the sequence is empty.
     *
     * @return mixed
     *
     */
    public function last();

    /**
     * Returns a new sequence using the results of applying a callback to each value.
     *
     * @param callable $callback
     *
     * @return SequenceInterface
     */
    public function map(callable $callback): SequenceInterface;

    /**
     * Returns the result of adding all given values to the sequence.
     *
     * @param array|\Traversable $values
     *
     * @return SequenceInterface
     */
    public function merge($values): SequenceInterface;

    /**
     * Removes the last value in the sequence, and returns it.
     *
     * @throws \UnderflowException if the sequence is empty.
     *
     * @return mixed what was the last value in the sequence.
     *
     */
    public function pop();

    /**
     * Adds zero or more values to the end of the sequence.
     *
     * @param mixed ...$values
     *
     * @return SequenceInterface
     */
    public function push(...$values): SequenceInterface;

    /**
     * Iteratively reduces the sequence to a single value using a callback.
     *
     * @param callable $callback Accepts the carry and current value, and returns an updated carry value.
     * @param mixed|null $initial Optional initial carry value.
     *
     * @return mixed The carry value of the final iteration, or the initial value if the sequence was empty.
     */
    public function reduce(callable $callback, $initial = null);

    /**
     * Removes and returns the value at a given index in the sequence.
     *
     * @param int $index this index to remove.
     *
     * @throws \OutOfRangeException if the index is not in the range [0, size-1]
     *
     * @return mixed the removed value.
     *
     */
    public function remove(int $index);

    /**
     * Reverses the sequence in-place.
     *
     * @return SequenceInterface
     */
    public function reverse(): SequenceInterface;

    /**
     * Rotates the sequence by a given number of rotations, which is equivalent
     * to successive calls to 'shift' and 'push' if the number of rotations is
     * positive, or 'pop' and 'unshift' if negative.
     *
     * @param int $rotations The number of rotations (can be negative).
     *
     * @return SequenceInterface
     */
    public function rotate(int $rotations) : SequenceInterface;

    /**
     * Replaces the value at a given index in the sequence with a new value.
     *
     * @param int $index
     * @param mixed $value
     *
     * @throws \OutOfRangeException if the index is not in the range [0, size-1]
     *
     * @return SequenceInterface
     */
    public function set(int $index, $value): SequenceInterface;

    /**
     * Removes and returns the first value in the sequence.
     *
     * @throws \UnderflowException if the sequence was empty.
     *
     * @return mixed what was the first value in the sequence.
     *
     */
    public function shift();

    /**
     * Returns a sub-sequence of a given length starting at a specified index.
     *
     * @param int $index If the index is positive, the sequence will start at that index in the sequence. If index is
     * negative, the sequence will start that far from the end.
     * @param int $length If a length is given and is positive, the resulting sequence will have up to that many values
     * in it. If the length results in an overflow, only values up to the end of the sequence will be included.
     *
     * If a length is given and is negative, the sequence will stop that many values from the end.
     *
     * If a length is not provided, the resulting sequence will contain all values between the index and the end of the
     * sequence.
     *
     * @return SequenceInterface
     */
    public function slice(int $index, int $length = null): SequenceInterface;

    /**
     * Returns a sorted copy of the sequence, based on an optional callable
     * comparator. Natural ordering will be used if a comparator is not given.
     *
     * @param callable|null $comparator Accepts two values to be compared. Should return the result of a <=> b.
     *
     * @return SequenceInterface
     */
    public function sort(callable $comparator = null): SequenceInterface;

    /**
     * Returns the sum of all values in the sequence.
     *
     * @return int|float The sum of all the values in the sequence.
     */
    public function sum();

    /**
     * Adds zero or more values to the front of the sequence.
     *
     * @param mixed ...$values
     *
     * @return SequenceInterface
     */
    public function unshift(...$values): SequenceInterface;
}
