<?php

namespace Altair\Structure;

use Altair\Structure\Contracts\CapacityInterface;
use Altair\Structure\Contracts\MapInterface;
use Altair\Structure\Contracts\PairInterface;
use Altair\Structure\Contracts\SetInterface;
use Altair\Structure\Contracts\VectorInterface;
use ArrayAccess;
use IteratorAggregate;
use OutOfBoundsException;
use OutOfRangeException;
use UnderflowException;

/**
 * Map.
 *
 * A Map is a sequential collection of key-value pairs, almost identical to an array when used in similar contexts.
 * Keys can be any type, but must be unique. Values are replaced if added to the map using the same key. Like an array,
 * insertion order is preserved.
 *
 * @link https://medium.com/@rtheunissen/efficient-data-structures-for-php-7-9dda7af674cd#.gl62k1xqr
 */
class Map implements IteratorAggregate, ArrayAccess, MapInterface, CapacityInterface
{
    use Traits\CollectionTrait;
    use Traits\SquaredCapacityTrait;

    /**
     * Creates an instance using the values of an array or Traversable object.
     *
     * @param array|\Traversable|MapInterface|null $values
     */
    public function __construct($values = [])
    {
        if (func_num_args()) {
            $this->putAll($this->normalizeItems($values));
        }
    }

    /**
     * Debug Info.
     */
    public function __debugInfo()
    {
        return $this->pairs()->toArray();
    }

    /**
     * {@inheritdoc}
     */
    public function apply(callable $callback): MapInterface
    {
        foreach ($this->internal as &$pair) {
            $pair->value = $callback($pair->key, $pair->value);
        }

        return new static($this);
    }

    /**
     * {@inheritdoc}
     */
    public function merge($values): MapInterface
    {
        $merged = new static($this);
        $merged->putAll($values);

        return $merged;
    }

    /**
     * {@inheritdoc}
     */
    public function intersect(MapInterface $map): MapInterface
    {
        return $this->filter(
            function ($key) use ($map) {
                return $map->hasKey($key);
            }
        );
    }

    /**
     * {@inheritdoc}
     */
    public function diff(MapInterface $map): MapInterface
    {
        return $this->filter(
            function ($key) use ($map) {
                return !$map->hasKey($key);
            }
        );
    }

    /**
     * {@inheritdoc}
     */
    public function filter(callable $callback = null): MapInterface
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
     * {@inheritdoc}
     */
    public function map(callable $callback): MapInterface
    {
        $mapped = new static();

        foreach ($this->internal as $pair) {
            $mapped[$pair->key] = $callback($pair->key, $pair->value);
        }

        return $mapped;
    }

    /**
     * {@inheritdoc}
     */
    public function put($key, $value): MapInterface
    {
        $pair = $this->lookupKey($key);

        if ($pair) {
            $pair->value = $value;
        } else {
            $this->adjustCapacity();
            $this->internal[] = new Pair($key, $value);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function putAll($values): MapInterface
    {
        foreach ($values as $key => $value) {
            $this->put($key, $value);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function reverse(): MapInterface
    {
        $pairs = array_reverse($this->internal);

        return new static($this->pairsToArray($pairs));
    }

    /**
     * {@inheritdoc}
     */
    public function slice(int $offset, int $length = null): MapInterface
    {
        if (func_num_args() === 1) {
            $slice = array_slice($this->internal, $offset);
        } else {
            $slice = array_slice($this->internal, $offset, $length);
        }

        return new static($this->pairsToArray($slice));
    }

    /**
     * {@inheritdoc}
     */
    public function sort(callable $comparator = null): MapInterface
    {
        $pairs = array_merge([], $this->internal);

        if ($comparator) {
            usort(
                $pairs,
                function ($a, $b) use ($comparator) {
                    return $comparator($a->value, $b->value);
                }
            );
        } else {
            usort(
                $pairs,
                function ($a, $b) {
                    return $a->value <=> $b->value;
                }
            );
        }

        return new static($this->pairsToArray($pairs));
    }

    /**
     * {@inheritdoc}
     */
    public function ksort(callable $comparator = null): MapInterface
    {
        $pairs = array_merge([], $this->internal);

        if ($comparator) {
            usort(
                $pairs,
                function ($a, $b) use ($comparator) {
                    return $comparator($a->key, $b->key);
                }
            );
        } else {
            usort(
                $pairs,
                function ($a, $b) {
                    return $a->key <=> $b->key;
                }
            );
        }

        return new static($this->pairsToArray($pairs));
    }

    /**
     * {@inheritdoc}
     */
    public function union(MapInterface $map): MapInterface
    {
        return $this->merge($map);
    }

    /**
     * {@inheritdoc}
     */
    public function xor(MapInterface $map): MapInterface
    {
        return $this->merge($map)->filter(
            function ($key) use ($map) {
                return $this->hasKey($key) ^ $map->hasKey($key);
            }
        );
    }

    /**
     * {@inheritdoc}
     */
    public function pairs(): VectorInterface
    {
        $sequence = new Vector();

        /** @var Pair $pair */
        foreach ($this->internal as $pair) {
            $sequence[] = $pair->copy();
        }

        return $sequence;
    }

    /**
     * {@inheritdoc}
     */
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
     * @return PairInterface
     *
     */
    public function first(): PairInterface
    {
        if ($this->isEmpty()) {
            throw new UnderflowException();
        }

        return $this->internal[0];
    }

    /**
     * {@inheritdoc}
     */
    public function last(): PairInterface
    {
        if ($this->isEmpty()) {
            throw new UnderflowException();
        }

        return end($this->internal);
    }

    /**
     * {@inheritdoc}
     */
    public function skip(int $position): PairInterface
    {
        if ($position < 0 || $position >= count($this->internal)) {
            throw new OutOfRangeException();
        }
        /** @var PairInterface $pair */
        $pair = $this->internal[$position];

        return $pair->copy();
    }

    /**
     * {@inheritdoc}
     */
    public function keys(): SetInterface
    {
        $set = new Set();

        foreach ($this->internal as $pair) {
            $set->add($pair->key);
        }

        return $set;
    }

    /**
     * {@inheritdoc}
     */
    public function hasKey($key): bool
    {
        return $this->lookupKey($key) !== null;
    }

    /**
     * {@inheritdoc}
     */
    public function hasValue($value): bool
    {
        return $this->lookupValue($value) !== null;
    }

    /**
     * {@inheritdoc}
     */
    public function get($key, $default = null)
    {
        if (($pair = $this->lookupKey($key))) {
            return $pair->value;
        }

        if (func_num_args() === 1) {
            throw new OutOfBoundsException();
        }

        return $default;
    }

    /**
     * {@inheritdoc}
     */
    public function reduce(callable $callback, $initial = null)
    {
        $carry = $initial;

        foreach ($this->internal as $pair) {
            $carry = $callback($carry, $pair->key, $pair->value);
        }

        return $carry;
    }

    /**
     * {@inheritdoc}
     */
    public function sum()
    {
        return $this->values()->sum();
    }

    /**
     * {@inheritdoc}
     */
    public function remove($key, $default = null)
    {
        /**
         * @var int
         * @var PairInterface $pair
         */
        foreach ($this->internal as $position => $pair) {
            if ($pair->equalsKey($key)) {
                return $this->delete($position);
            }
        }

        // Check if a default was provided
        if (func_num_args() === 1) {
            throw new OutOfBoundsException();
        }

        return $default;
    }

    /**
     * {@inheritdoc}
     */
    public function toArray(): array
    {
        return $this->pairsToArray($this->internal);
    }

    /**
     * Get iterator.
     */
    public function getIterator()
    {
        foreach ($this->internal as $pair) {
            yield $pair->key => $pair->value;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function offsetSet($offset, $value)
    {
        $this->put($offset, $value);
    }

    /**
     * {@inheritdoc}
     *
     * @throws OutOfBoundsException
     */
    public function &offsetGet($offset)
    {
        $pair = $this->lookupKey($offset);

        if ($pair) {
            return $pair->value;
        }

        throw new OutOfBoundsException();
    }

    /**
     * {@inheritdoc}
     */
    public function offsetUnset($offset)
    {
        $this->remove($offset, null);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetExists($offset)
    {
        return $this->get($offset, null) !== null;
    }

    /**
     * Returns item if a key is found.
     *
     * @param mixed $key
     *
     * @return PairInterface|null
     */
    protected function lookupKey($key): ?PairInterface
    {
        /** @var PairInterface $pair */
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
     * @param mixed $value
     *
     * @return PairInterface|null
     */
    protected function lookupValue($value): ?PairInterface
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
     * @param int $position
     *
     * @return mixed
     */
    protected function delete(int $position)
    {
        /** @var PairInterface $pair */
        $pair = $this->internal[$position];
        $value = $pair->value;

        array_splice($this->internal, $position, 1, null);

        $this->adjustCapacity();

        return $value;
    }

    /**
     * Converts pairs to array.
     *
     * @param $pairs
     *
     * @return array
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
