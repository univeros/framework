<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Structure;

use Altair\Structure\Contracts\CapacityInterface;
use Altair\Structure\Contracts\MapInterface;
use Altair\Structure\Contracts\SetInterface;
use ArrayAccess;
use Error;
use IteratorAggregate;
use OutOfBoundsException;

/**
 * Set.
 *
 * A Set is a collection of unique values. The textbook definition of a set will say that values are unordered unless an
 * implementation specifies otherwise. Using Java as an example, java.util.Set is an interface with two primary
 * implementations: HashSet and TreeSet. HashSet provides O(1) add and remove, where TreeSet ensures a sorted set but
 * O(log n) add and remove.
 *
 * Set uses the same internal structure as a Map, which is based on the same structure as an array. This means that a
 * Set can be sorted in O(n * log(n)) time whenever it needs to be, just like a Map and an array.
 *
 * @link https://medium.com/@rtheunissen/efficient-data-structures-for-php-7-9dda7af674cd#.gl62k1xqr
 */
class Set implements IteratorAggregate, ArrayAccess, SetInterface, CapacityInterface
{
    use Traits\CollectionTrait;

    /**
     * Creates a new set using the values of an array or Traversable object.
     * The keys of either will not be preserved.
     *
     * @param array|\Traversable|null $values
     */
    public function __construct($values = null)
    {
        $this->internal = new Map();

        if (func_num_args()) {
            $this->add(...$values);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function add(...$values): SetInterface
    {
        foreach ($values as $value) {
            $this->internal->put($value, null);
        }

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function allocate(int $capacity): SetInterface
    {
        $this->internal->allocate($capacity);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function diff(SetInterface $set): SetInterface
    {
        return $this->internal->diff($set->getMap())->keys();
    }

    /**
     * {@inheritDoc}
     */
    public function xor(SetInterface $set): SetInterface
    {
        return $this->internal->xor($set->getMap())->keys();
    }

    /**
     * {@inheritDoc}
     */
    public function capacity(): int
    {
        return $this->internal->capacity();
    }

    /**
     * {@inheritDoc}
     */
    public function contains(...$values): bool
    {
        foreach ($values as $value) {
            if (!$this->internal->hasKey($value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function filter(callable $callback = null): SetInterface
    {
        return new static(array_filter($this->toArray(), $callback ?: 'boolval'));
    }

    /**
     * Returns the first value in the set.
     *
     * @return mixed the first value in the set.
     */
    public function first()
    {
        return $this->internal->first()->key;
    }

    /**
     * Returns the last value in the set.
     *
     * @return mixed the last value in the set.
     */
    public function last()
    {
        return $this->internal->last()->key;
    }

    /**
     * {@inheritDoc}
     */
    public function get(int $position)
    {
        return $this->internal->skip($position)->key;
    }

    /**
     * {@inheritDoc}
     */
    public function intersect(SetInterface $set): SetInterface
    {
        return $this->internal->intersect($set->getMap())->keys();
    }

    /**
     * {@inheritDoc}
     */
    public function join(string $glue = null): string
    {
        return implode($glue, $this->toArray());
    }

    /**
     * {@inheritDoc}
     */
    public function reduce(callable $callback, $initial = null)
    {
        $carry = $initial;

        foreach ($this as $value) {
            $carry = $callback($carry, $value);
        }

        return $carry;
    }

    /**
     * {@inheritDoc}
     */
    public function remove(...$values)
    {
        foreach ($values as $value) {
            $this->internal->remove($value, null);
        }
    }

    /**
     * Returns a reversed copy of the set.
     *
     * @return SetInterface
     */
    public function reverse(): SetInterface
    {
        $values = array_reverse($this->internal->keys()->toArray());

        return new static($values);
    }

    /**
     * {@inheritDoc}
     */
    public function slice(int $offset, int $length = null): SetInterface
    {
        return new static($this->internal->slice($offset, $length)->keys());
    }

    /**
     * {@inheritDoc}
     */
    public function sort(callable $comparator = null): SetInterface
    {
        return new static($this->getMap()->ksort($comparator)->keys());
    }

    /**
     * Returns the result of adding all given values to the set.
     *
     * @param array|\Traversable $values
     *
     * @return SetInterface
     */
    public function merge($values): SetInterface
    {
        $merged = $this->copy();

        foreach ($values as $value) {
            $merged->add($value);
        }

        return $merged;
    }

    /**
     * Returns the sum of all values in the set.
     *
     * @return int|float The sum of all the values in the set.
     */
    public function sum()
    {
        return array_sum($this->toArray());
    }

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
    public function union(SetInterface $set): SetInterface
    {
        $union = new static();

        foreach ($this as $value) {
            $union->add($value);
        }

        foreach ($set as $value) {
            $union->add($value);
        }

        return $union;
    }

    /**
     * {@inheritDoc}
     */
    public function getMap(): MapInterface
    {
        return $this->internal;
    }

    /**
     * {@inheritDoc}
     */
    public function isEmpty(): bool
    {
        return $this->internal->isEmpty();
    }

    /**
     * {@inheritDoc}
     */
    public function toArray(): array
    {
        return iterator_to_array($this);
    }

    /**
     * Get iterator.
     */
    public function getIterator()
    {
        foreach ($this->internal as $key => $value) {
            yield $key;
        }
    }

    /**
     * {@inheritDoc}
     *
     * @throws OutOfBoundsException
     */
    public function offsetSet($offset, $value)
    {
        if ($offset === null) {
            $this->add($value);

            return;
        }

        throw new OutOfBoundsException();
    }

    /**
     * {@inheritDoc}
     */
    public function offsetGet($offset)
    {
        return $this->internal->skip($offset)->key;
    }

    /**
     * {@inheritDoc}
     *
     * @throws Error
     */
    public function offsetExists($offset)
    {
        throw new Error();
    }

    /**
     * {@inheritDoc}
     *
     * @throws Error
     */
    public function offsetUnset($offset)
    {
        throw new Error();
    }
}
