<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Structure\Traits;

use Altair\Structure\Contracts\CapacityInterface;
use Altair\Structure\Contracts\SequenceInterface;
use Generator;
use OutOfRangeException;
use ReturnTypeWillChange;
use Traversable;
use UnderflowException;

/**
 * Common functionality of all structures that implement 'Sequence'.
 *
 * @template TValue
 *
 * @use CollectionTrait<int, TValue>
 */
trait SequenceTrait
{
    /** @use CollectionTrait<int, TValue> */
    use CollectionTrait;

    /**
     * {@inheritDoc}
     *
     * @return array<int, TValue>
     */
    public function toArray(): array
    {
        return $this->internal;
    }

    /**
     * {@inheritDoc}
     *
     * @return SequenceInterface<TValue>
     */
    public function apply(callable $callback): SequenceInterface
    {
        foreach ($this->internal as &$value) {
            $value = $callback($value);
        }

        return new static($this->internal);
    }

    /**
     * {@inheritDoc}
     *
     * @param array<array-key, TValue>|Traversable<array-key, TValue> $values
     *
     * @return SequenceInterface<TValue>
     */
    public function merge($values): SequenceInterface
    {
        if (!\is_array($values)) {
            $values = iterator_to_array($values);
        }

        return new static(array_merge($this->internal, $values));
    }

    /**
     * {@inheritDoc}
     *
     * @param TValue ...$values
     */
    public function contains(mixed ...$values): bool
    {
        foreach ($values as $value) {
            if ($this->find($value) === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * {@inheritDoc}
     *
     * @return SequenceInterface<TValue>
     */
    public function filter(?callable $callback = null): SequenceInterface
    {
        return new static(array_filter($this->internal, $callback ?: 'boolval'));
    }

    /**
     * {@inheritDoc}
     *
     * @param TValue $value
     */
    public function find(mixed $value): int|string|false
    {
        return array_search($value, $this->internal, true);
    }

    /**
     * {@inheritDoc}
     *
     * @return TValue
     */
    public function first()
    {
        if (empty($this->internal)) {
            throw new UnderflowException('Is empty');
        }

        return $this->internal[0];
    }

    /**
     * {@inheritDoc}
     *
     * @return TValue
     */
    public function get(int $index)
    {
        $this->checkRange($index);

        return $this->internal[$index];
    }

    /**
     * {@inheritDoc}
     *
     * @param TValue ...$values
     *
     * @return SequenceInterface<TValue>
     */
    public function insert(int $index, mixed ...$values): SequenceInterface
    {
        if ($index < 0 || $index > \count($this->internal)) {
            throw new OutOfRangeException('Out of bounds');
        }

        array_splice($this->internal, $index, 0, $values);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function join(?string $glue = null): string
    {
        return implode($glue ?? '', $this->internal);
    }

    /**
     * {@inheritDoc}
     *
     * @return TValue
     */
    public function last(): mixed
    {
        if ($this->isEmpty()) {
            throw new UnderflowException('Is empty');
        }

        return end($this->internal);
    }

    /**
     * {@inheritDoc}
     *
     * @return SequenceInterface<TValue>
     */
    public function map(callable $callback): SequenceInterface
    {
        return new static(array_map($callback, $this->internal));
    }

    /**
     * {@inheritDoc}
     *
     * @return TValue
     */
    public function pop()
    {
        if ($this->isEmpty()) {
            throw new UnderflowException('Is empty');
        }

        $value = array_pop($this->internal);
        if (method_exists($this, 'adjustCapacity')) {
            $this->adjustCapacity();
        }

        return $value;
    }

    /**
     * {@inheritDoc}
     *
     * @param TValue ...$values
     *
     * @return SequenceInterface<TValue>
     */
    public function push(mixed ...$values): SequenceInterface
    {
        $this->pushAll($values);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function reduce(callable $callback, $initial = null)
    {
        return array_reduce($this->internal, $callback, $initial);
    }

    /**
     * {@inheritDoc}
     *
     * @return TValue
     */
    public function remove(int $index)
    {
        $this->checkRange($index);

        $value = array_splice($this->internal, $index, 1, null)[0];
        if (method_exists($this, 'adjustCapacity')) {
            $this->adjustCapacity();
        }

        return $value;
    }

    /**
     * {@inheritDoc}
     *
     * @return SequenceInterface<TValue>
     */
    public function reverse(): SequenceInterface
    {
        return new static(array_reverse($this->internal));
    }

    /**
     * {@inheritDoc}
     *
     * @return SequenceInterface<TValue>
     */
    public function rotate(int $rotations): SequenceInterface
    {
        if (\count($this) < 2) {
            return $this;
        }

        $rotations = $this->normalizeRotations($rotations, \count($this));

        while ($rotations--) {
            $this->push($this->shift());
        }

        // MODIFIED
        return new static($this);
    }

    /**
     * {@inheritDoc}
     *
     * @param TValue $value
     *
     * @return SequenceInterface<TValue>
     */
    public function set(int $index, mixed $value): SequenceInterface
    {
        $this->checkRange($index);
        $this->internal[$index] = $value;

        return $this;
    }

    /**
     * {@inheritDoc}
     *
     * @return TValue
     */
    public function shift()
    {
        if ($this->isEmpty()) {
            throw new UnderflowException('Is empty');
        }

        $value = array_shift($this->internal);
        if (method_exists($this, 'adjustCapacity')) {
            $this->adjustCapacity();
        }

        return $value;
    }

    /**
     * {@inheritDoc}
     *
     * @return SequenceInterface<TValue>
     */
    public function slice(int $offset, ?int $length = null): SequenceInterface
    {
        if (\func_num_args() === 1) {
            $length = \count($this);
        }

        return new static(\array_slice($this->internal, $offset, $length));
    }

    /**
     * {@inheritDoc}
     *
     * @return SequenceInterface<TValue>
     */
    public function sort(?callable $comparator = null): SequenceInterface
    {
        $internal = $this->internal;

        if ($comparator !== null) {
            usort($internal, $comparator);
        } else {
            sort($internal);
        }

        return new static($internal);
    }

    /**
     * {@inheritDoc}
     */
    public function sum(): float|int
    {
        return array_sum($this->internal);
    }

    /**
     * {@inheritDoc}
     *
     * @param TValue ...$values
     *
     * @return SequenceInterface<TValue>
     */
    public function unshift(mixed ...$values): SequenceInterface
    {
        if ($values !== []) {
            array_unshift($this->internal, ...$values);
            if ($this instanceof CapacityInterface) {
                $this->adjustCapacity();
            }
        }

        return new static($this->internal);
    }

    /**
     * @return Generator<int, TValue>
     */
    #[ReturnTypeWillChange]
    public function getIterator()
    {
        foreach ($this->internal as $value) {
            yield $value;
        }
    }

    /**
     * {@inheritDoc}
     *
     * @param TValue $value
     */
    #[ReturnTypeWillChange]
    public function offsetSet($offset, mixed $value): void
    {
        if ($offset === null) {
            $this->push($value);
        } else {
            $this->set($offset, $value);
        }
    }

    /**
     * {@inheritDoc}
     *
     * @return TValue
     */
    #[ReturnTypeWillChange]
    public function &offsetGet($offset)
    {
        $this->checkRange($offset);

        return $this->internal[$offset];
    }

    /**
     * {@inheritDoc}
     */
    #[ReturnTypeWillChange]
    public function offsetUnset($offset): void
    {
        // Unset should be quiet, so we shouldn't allow 'remove' to throw.
        if (\is_int($offset) && $offset >= 0 && $offset < \count($this)) {
            $this->remove($offset);
        }
    }

    /**
     * {@inheritDoc}
     */
    #[ReturnTypeWillChange]
    public function offsetExists($offset)
    {
        if ($offset < 0 || $offset >= \count($this)) {
            return false;
        }

        return $this->get($offset) !== null;
    }

    protected function checkRange(int $index): void
    {
        if ($index < 0 || $index >= \count($this->internal)) {
            throw new OutOfRangeException('Out of range');
        }
    }

    protected function normalizeRotations(int $rotations, int $count): int
    {
        if ($rotations < 0) {
            return $count - (abs($rotations) % $count);
        }

        return $rotations % $count;
    }
}
