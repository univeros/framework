<?php
namespace Altair\Structure\Contracts;

use Altair\Structure\Pair;
use Altair\Structure\Set;

interface MapInterface extends CollectionInterface
{
    /**
     * Updates all values by applying a callback function to each value.
     *
     * @param callable $callback Accepts two arguments: key and value, should return what the updated value will be.
     *
     * @return MapInterface
     */
    public function apply(callable $callback): MapInterface;

    /**
     * Merge an array of values with the current Map.
     *
     * @param array|\Traversable $values
     *
     * @return MapInterface
     */
    public function merge($values): MapInterface;

    /**
     * Intersect.
     *
     * @param MapInterface $map
     *
     * @return MapInterface
     */
    public function intersect(MapInterface $map): MapInterface;

    /**
     * Diff.
     *
     * @param MapInterface $map
     *
     * @return MapInterface
     */
    public function diff(MapInterface $map): MapInterface;

    /**
     * Returns a new map containing only the values for which a predicate returns true. A boolean test will be used if
     * a predicate is not provided.
     *
     * @param callable $callback Accepts a key and a value, and returns: true : include the value, false: skip the
     * value.
     *
     * @return MapInterface
     */
    public function filter(callable $callback): MapInterface;

    /**
     * Returns a new map using the results of applying a callback to each value.
     *
     * The keys will be equal in both maps.
     *
     * @param callable $callback Accepts two arguments: key and value, should return what the updated value will be.
     *
     * @return MapInterface
     */
    public function map(callable $callback): MapInterface;

    /**
     * Associates a key with a value, replacing a previous association if there
     * was one.
     *
     * @param mixed $key
     * @param mixed $value
     *
     * @return MapInterface
     */
    public function put($key, $value): MapInterface;

    /**
     * Creates associations for all keys and corresponding values of either an array or iterable object.
     *
     * @param \Traversable|array $values
     *
     * @return MapInterface
     */
    public function putAll($values): MapInterface;

    /**
     * Returns a reversed copy of the map.
     *
     * @return MapInterface
     */
    public function reverse(): MapInterface;

    /**
     * Returns a sub-sequence of a given length starting at a specified offset.
     *
     * @param int $offset If the offset is non-negative, the map will start at that offset in the map. If offset is
     * negative, the map will start that far from the end.
     * @param int|null $length If a length is given and is positive, the resulting set will have up to that many pairs
     * in it. If the requested length results in an overflow, only pairs up to the end of the map will be included.
     *
     * If a length is given and is negative, the map will stop that many pairs from the end.
     *
     * If a length is not provided, the resulting map will contains all pairs between the offset and the end of the map.
     *
     * @return MapInterface
     */
    public function slice(int $offset, int $length = null): MapInterface;

    /**
     * Returns a sorted copy of the map, based on an optional callable
     * comparator. The map will be sorted by value.
     *
     * @param callable|null $comparator Accepts two values to be compared.
     *
     * @return MapInterface
     */
    public function sort(callable $comparator = null): MapInterface;

    /**
     * Returns a sorted copy of the map, based on an optional callable
     * comparator. The map will be sorted by key.
     *
     * @param callable|null $comparator Accepts two keys to be compared.
     *
     * @return MapInterface
     */
    public function ksort(callable $comparator = null): MapInterface;

    /**
     * Merges two maps.
     *
     * @param MapInterface $map
     *
     * @return MapInterface
     */
    public function union(MapInterface $map): MapInterface;

    /**
     * XOR two maps.
     *
     * @param MapInterface $map
     *
     * @return MapInterface
     */
    public function xor(MapInterface $map): MapInterface;

    /**
     * Returns a sequence of pairs representing all associations.
     *
     * @return VectorInterface
     */
    public function pairs(): VectorInterface;

    /**
     * Returns a sequence of all the associated values in the Map.
     *
     * @return VectorInterface
     */
    public function values(): VectorInterface;

    /**
     * Return the first Pair from the Map.
     *
     * @throws \UnderflowException
     *
     * @return PairInterface
     *
     */
    public function first(): PairInterface;

    /**
     * Return the last Pair from the Map.
     *
     * @throws \UnderflowException
     *
     * @return PairInterface
     *
     */
    public function last(): PairInterface;

    /**
     * Return the pair at a specified position in the Map.
     *
     * @param int $position
     *
     * @throws \OutOfRangeException
     *
     * @return PairInterface
     *
     */
    public function skip(int $position): PairInterface;

    /**
     * Returns a set of all the keys in the map.
     *
     * @return SetInterface
     */
    public function keys(): SetInterface;

    /**
     * Returns whether an association a given key exists.
     *
     * @param mixed $key
     *
     * @return bool
     */
    public function hasKey($key): bool;

    /**
     * Returns whether an association for a given value exists.
     *
     * @param mixed $value
     *
     * @return bool
     */
    public function hasValue($value): bool;

    /**
     * Returns the value associated with a key, or an optional default if the
     * key is not associated with a value.
     *
     * @param mixed $key
     * @param mixed $default
     *
     * @throws \OutOfBoundsException if no default was provided and the key isnot associated with a value.
     *
     * @return mixed The associated value or fallback default if provided.
     *
     */
    public function get($key, $default = null);

    /**
     * Iteratively reduces the map to a single value using a callback.
     *
     * @param callable $callback Accepts the carry, key, and value, and returns an updated carry value.
     * @param mixed|null $initial Optional initial carry value.
     *
     * @return mixed The carry value of the final iteration, or the initial value if the map was empty.
     */
    public function reduce(callable $callback, $initial = null);

    /**
     * Returns the sum of all values in the map.
     *
     * @return int|float The sum of all the values in the map.
     */
    public function sum();

    /**
     * Removes a key's association from the map and returns the associated value or a provided default if provided.
     *
     * @param mixed $key
     * @param mixed $default
     *
     * @throws \OutOfBoundsException if no default was provided and the key is not associated with a value.
     *
     * @return mixed The associated value or fallback default if provided.
     *
     */
    public function remove($key, $default = null);
}
