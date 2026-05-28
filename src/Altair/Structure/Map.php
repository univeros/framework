<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Structure;

use Altair\Structure\Contracts\CapacityInterface;
use Altair\Structure\Contracts\MapInterface;
use Altair\Structure\Contracts\PairInterface;
use Altair\Structure\Contracts\SetInterface;
use Altair\Structure\Contracts\VectorInterface;
use Altair\Structure\Traits\CollectionTrait;
use Altair\Structure\Traits\SquaredCapacityTrait;
use ArrayAccess;
use Generator;
use IteratorAggregate;
use OutOfBoundsException;
use OutOfRangeException;
use Override;
use ReturnTypeWillChange;
use Traversable;
use UnderflowException;

/**
 * Map.
 *
 * A Map is a sequential collection of key-value pairs, almost identical to an array when used in similar contexts.
 * Keys can be any type, but must be unique. Values are replaced if added to the map using the same key. Like an array,
 * insertion order is preserved.
 *
 * @link https://medium.com/@rtheunissen/efficient-data-structures-for-php-7-9dda7af674cd#.gl62k1xqr
 *
 * @template TKey
 * @template TValue
 *
 * @implements MapInterface<TKey, TValue>
 * @implements IteratorAggregate<TKey, TValue>
 * @implements ArrayAccess<TKey, TValue>
 */
class Map implements IteratorAggregate, ArrayAccess, MapInterface, CapacityInterface
{
    /** @use CollectionTrait<TKey, TValue> */
    use CollectionTrait;
    use SquaredCapacityTrait;

    /**
     * Internal storage of key/value associations as a list of pairs.
     *
     * Typed against the concrete Pair (not PairInterface) so the public
     * $key/$value properties resolve; the Map only ever stores Pair instances.
     *
     * @var array<int, Pair<TKey, TValue>>
     */
    protected $internal = [];

    /**
     * Creates an instance using the values of an array or Traversable object.
     *
     * @param array<array-key, TValue>|Traversable<TKey, TValue>|MapInterface<TKey, TValue>|null $values
     */
    public function __construct($values = [])
    {
        if (\func_num_args() !== 0) {
            $this->putAll($this->normalizeItems($values));
        }
    }

    /**
     * Debug Info.
     *
     * @return array<int, PairInterface<TKey, TValue>>
     */
    public function __debugInfo()
    {
        return $this->pairs()->toArray();
    }

    /**
     * {@inheritDoc}
     *
     * @return MapInterface<TKey, TValue>
     */
    #[Override]
    public function apply(callable $callback): MapInterface
    {
        foreach ($this->internal as &$pair) {
            $pair->value = $callback($pair->key, $pair->value);
        }

        return new static($this);
    }

    /**
     * {@inheritDoc}
     *
     * @param array<array-key, TValue>|Traversable<TKey, TValue> $values
     *
     * @return MapInterface<TKey, TValue>
     */
    #[Override]
    public function merge($values): MapInterface
    {
        $merged = new static($this);
        $merged->putAll($values);

        return $merged;
    }

    /**
     * {@inheritDoc}
     *
     * @param MapInterface<TKey, TValue> $map
     *
     * @return MapInterface<TKey, TValue>
     */
    #[Override]
    public function intersect(MapInterface $map): MapInterface
    {
        return $this->filter(
            static fn($key): bool => $map->hasKey($key)
        );
    }

    /**
     * {@inheritDoc}
     *
     * @param MapInterface<TKey, TValue> $map
     *
     * @return MapInterface<TKey, TValue>
     */
    #[Override]
    public function diff(MapInterface $map): MapInterface
    {
        return $this->filter(
            static fn($key): bool => !$map->hasKey($key)
        );
    }

    /**
     * {@inheritDoc}
     *
     * @return MapInterface<TKey, TValue>
     */
    #[Override]
    public function filter(?callable $callback = null): MapInterface
    {
        $filtered = new static();

        foreach ($this as $key => $value) {
            if ($callback ? $callback($key, $value) : $value) {
                $filtered->put($key, $value);
            }
        }

        return $filtered;
    }

    /**
     * {@inheritDoc}
     *
     * @return MapInterface<TKey, TValue>
     */
    #[Override]
    public function map(callable $callback): MapInterface
    {
        $mapped = new static();

        foreach ($this->internal as $pair) {
            $mapped[$pair->key] = $callback($pair->key, $pair->value);
        }

        return $mapped;
    }

    /**
     * {@inheritDoc}
     *
     * @param TKey $key
     * @param TValue $value
     *
     * @return MapInterface<TKey, TValue>
     */
    #[Override]
    public function put($key, $value): MapInterface
    {
        $pair = $this->lookupKey($key);

        if ($pair instanceof PairInterface) {
            $pair->value = $value;
        } else {
            $this->adjustCapacity();
            $this->internal[] = new Pair($key, $value);
        }

        return $this;
    }

    /**
     * {@inheritDoc}
     *
     * @param array<array-key, TValue>|Traversable<TKey, TValue> $values
     *
     * @return MapInterface<TKey, TValue>
     */
    #[Override]
    public function putAll($values): MapInterface
    {
        foreach ($values as $key => $value) {
            $this->put($key, $value);
        }

        return $this;
    }

    /**
     * {@inheritDoc}
     *
     * @return MapInterface<TKey, TValue>
     */
    #[Override]
    public function reverse(): MapInterface
    {
        $pairs = array_reverse($this->internal);

        return new static($this->pairsToArray($pairs));
    }

    /**
     * {@inheritDoc}
     *
     * @return MapInterface<TKey, TValue>
     */
    #[Override]
    public function slice(int $offset, ?int $length = null): MapInterface
    {
        $slice = \func_num_args() === 1 ? \array_slice($this->internal, $offset) : \array_slice($this->internal, $offset, $length);

        return new static($this->pairsToArray($slice));
    }

    /**
     * {@inheritDoc}
     *
     * @return MapInterface<TKey, TValue>
     */
    #[Override]
    public function sort(?callable $comparator = null): MapInterface
    {
        $pairs = array_merge([], $this->internal);

        if ($comparator !== null) {
            usort(
                $pairs,
                static fn($a, $b) => $comparator($a->value, $b->value)
            );
        } else {
            usort(
                $pairs,
                static fn($a, $b): int => $a->value <=> $b->value
            );
        }

        return new static($this->pairsToArray($pairs));
    }

    /**
     * {@inheritDoc}
     *
     * @return MapInterface<TKey, TValue>
     */
    #[Override]
    public function ksort(?callable $comparator = null): MapInterface
    {
        $pairs = array_merge([], $this->internal);

        if ($comparator !== null) {
            usort(
                $pairs,
                static fn($a, $b) => $comparator($a->key, $b->key)
            );
        } else {
            usort(
                $pairs,
                static fn($a, $b): int => $a->key <=> $b->key
            );
        }

        return new static($this->pairsToArray($pairs));
    }

    /**
     * {@inheritDoc}
     *
     * @param MapInterface<TKey, TValue> $map
     *
     * @return MapInterface<TKey, TValue>
     */
    #[Override]
    public function union(MapInterface $map): MapInterface
    {
        return $this->merge($map);
    }

    /**
     * {@inheritDoc}
     *
     * @param MapInterface<TKey, TValue> $map
     *
     * @return MapInterface<TKey, TValue>
     */
    #[Override]
    public function xor(MapInterface $map): MapInterface
    {
        return $this->merge($map)->filter(
            fn($key): int => $this->hasKey($key) ^ $map->hasKey($key)
        );
    }

    /**
     * {@inheritDoc}
     *
     * @return VectorInterface<PairInterface<TKey, TValue>>
     */
    #[Override]
    public function pairs(): VectorInterface
    {
        $sequence = new Vector();

        foreach ($this->internal as $pair) {
            $sequence[] = $pair->copy();
        }

        return $sequence;
    }

    /**
     * {@inheritDoc}
     *
     * @return VectorInterface<TValue>
     */
    #[Override]
    public function values(): VectorInterface
    {
        $sequence = new Vector();

        foreach ($this->internal as $pair) {
            $sequence->push($pair->value);
        }

        return $sequence;
    }

    /**
     * Return the first Pair from the Map.
     *
     * @throws UnderflowException
     *
     * @return Pair<TKey, TValue>
     */
    #[Override]
    public function first(): PairInterface
    {
        if ($this->isEmpty()) {
            throw new UnderflowException('Map is empty');
        }

        return $this->internal[0];
    }

    /**
     * {@inheritDoc}
     *
     * @return Pair<TKey, TValue>
     */
    #[Override]
    public function last(): PairInterface
    {
        if ($this->isEmpty()) {
            throw new UnderflowException('Map is empty');
        }

        return end($this->internal);
    }

    /**
     * {@inheritDoc}
     *
     * @return Pair<TKey, TValue>
     */
    #[Override]
    public function skip(int $position): PairInterface
    {
        if ($position < 0 || $position >= \count($this->internal)) {
            throw new OutOfRangeException('Out of range');
        }

        $pair = $this->internal[$position];

        return $pair->copy();
    }

    /**
     * {@inheritDoc}
     *
     * @return SetInterface<TKey>
     */
    #[Override]
    public function keys(): SetInterface
    {
        $set = new Set();

        foreach ($this->internal as $pair) {
            $set->add($pair->key);
        }

        return $set;
    }

    /**
     * {@inheritDoc}
     *
     * @param TKey $key
     */
    #[Override]
    public function hasKey($key): bool
    {
        return $this->lookupKey($key) instanceof PairInterface;
    }

    /**
     * {@inheritDoc}
     *
     * @param TValue $value
     */
    #[Override]
    public function hasValue($value): bool
    {
        return $this->lookupValue($value) instanceof PairInterface;
    }

    /**
     * {@inheritDoc}
     *
     * @param TKey $key
     * @param TValue|null $default
     *
     * @return TValue
     */
    #[Override]
    public function get($key, $default = null)
    {
        if (($pair = $this->lookupKey($key)) instanceof PairInterface) {
            return $pair->value;
        }

        return $default;
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function reduce(callable $callback, $initial = null)
    {
        $carry = $initial;

        foreach ($this->internal as $pair) {
            $carry = $callback($carry, $pair->key, $pair->value);
        }

        return $carry;
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function sum()
    {
        return $this->values()->sum();
    }

    /**
     * {@inheritDoc}
     *
     * @param TKey $key
     * @param TValue|null $default
     *
     * @return TValue
     */
    #[Override]
    public function remove($key, $default = null)
    {
        foreach ($this->internal as $position => $pair) {
            if ($pair->equalsKey($key)) {
                return $this->delete($position);
            }
        }

        return $default;
    }

    /**
     * {@inheritDoc}
     *
     * @return array<array-key, TValue>
     */
    #[Override]
    public function toArray(): array
    {
        return $this->pairsToArray($this->internal);
    }

    /**
     * Get iterator.
     *
     * @return Generator<TKey, TValue>
     */
    #[ReturnTypeWillChange]
    #[Override]
    public function getIterator()
    {
        foreach ($this->internal as $pair) {
            yield $pair->key => $pair->value;
        }
    }

    /**
     * {@inheritDoc}
     *
     * @param TKey $offset
     * @param TValue $value
     */
    #[ReturnTypeWillChange]
    #[Override]
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->put($offset, $value);
    }

    /**
     * {@inheritDoc}
     *
     * @param TKey $offset
     *
     * @throws OutOfBoundsException
     *
     * @return TValue
     */
    #[ReturnTypeWillChange]
    #[Override]
    public function &offsetGet(mixed $offset)
    {
        $pair = $this->lookupKey($offset);

        if ($pair instanceof PairInterface) {
            return $pair->value;
        }

        throw new OutOfBoundsException('Out of bounds');
    }

    /**
     * {@inheritDoc}
     */
    #[ReturnTypeWillChange]
    #[Override]
    public function offsetUnset($offset): void
    {
        $this->remove($offset);
    }

    /**
     * {@inheritDoc}
     */
    #[ReturnTypeWillChange]
    #[Override]
    public function offsetExists($offset)
    {
        return $this->get($offset) !== null;
    }

    /**
     * Returns item if a key is found.
     *
     * @param TKey $key
     *
     * @return Pair<TKey, TValue>|null
     */
    protected function lookupKey(mixed $key): ?PairInterface
    {
        foreach ($this->internal as $pair) {
            if ($pair->equalsKey($key)) {
                return $pair;
            }
        }

        return null;
    }

    /**
     * Returns item if a value is found.
     *
     * @param TValue $value
     *
     * @return Pair<TKey, TValue>|null
     */
    protected function lookupValue(mixed $value): ?PairInterface
    {
        foreach ($this->internal as $pair) {
            if ($pair->value === $value) {
                return $pair;
            }
        }

        return null;
    }

    /**
     * Removes an Map item at specified position.
     *
     * @return TValue
     */
    protected function delete(int $position)
    {
        $pair = $this->internal[$position];
        $value = $pair->value;

        array_splice($this->internal, $position, 1, null);

        $this->adjustCapacity();

        return $value;
    }

    /**
     * Converts pairs to array.
     *
     * @param iterable<int, Pair<TKey, TValue>> $pairs
     *
     * @return array<array-key, TValue>
     */
    protected function pairsToArray($pairs): array
    {
        $array = [];
        foreach ($pairs as $pair) {
            $array[$pair->key] = $pair->value;
        }

        return $array;
    }
}
