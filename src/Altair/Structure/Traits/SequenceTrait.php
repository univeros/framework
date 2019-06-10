<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Structure\Traits;

use Altair\Structure\Contracts\CapacityInterface;
use Altair\Structure\Contracts\SequenceInterface;
use OutOfRangeException;
use UnderflowException;

/**
 * Common functionality of all structures that implement 'Sequence'.
 */
trait SequenceTrait
{
    use CollectionTrait;

    /**
     * {@inheritDoc}
     */
    public function toArray(): array
    {
        return $this->internal;
    }

    /**
     * {@inheritDoc}
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
     */
    public function merge($values): SequenceInterface
    {
        if (!is_array($values)) {
            $values = iterator_to_array($values);
        }

        return new static(array_merge($this->internal, $values));
    }

    /**
     * {@inheritDoc}
     */
    public function contains(...$values): bool
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
     */
    public function filter(callable $callback = null): SequenceInterface
    {
        return new static(array_filter($this->internal, $callback ?: 'boolval'));
    }

    /**
     * {@inheritDoc}
     */
    public function find($value)
    {
        return array_search($value, $this->internal, true);
    }

    /**
     * {@inheritDoc}
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
     */
    public function get(int $index)
    {
        $this->checkRange($index);

        return $this->internal[$index];
    }

    /**
     * {@inheritDoc}
     */
    public function insert(int $index, ...$values): SequenceInterface
    {
        if ($index < 0 || $index > count($this->internal)) {
            throw new OutOfRangeException('Out of bounds');
        }

        return new static(array_splice($this->internal, $index, 0, $values));
    }

    /**
     * {@inheritDoc}
     */
    public function join(string $glue = null): string
    {
        return implode($glue, $this->internal);
    }

    /**
     * {@inheritDoc}
     */
    public function last()
    {
        if ($this->isEmpty()) {
            throw new UnderflowException('Is empty');
        }

        return end($this->internal);
    }

    /**
     * {@inheritDoc}
     */
    public function map(callable $callback): SequenceInterface
    {
        return new static(array_map($callback, $this->internal));
    }

    /**
     * {@inheritDoc}
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
     */
    public function push(...$values): SequenceInterface
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
     */
    public function reverse(): SequenceInterface
    {
        return new static(array_reverse($this->internal));
    }

    /**
     * {@inheritDoc}
     */
    public function rotate(int $rotations): SequenceInterface
    {
        if (count($this) < 2) {
            return $this;
        }

        $rotations = $this->normalizeRotations($rotations, count($this));

        while ($rotations--) {
            $this->push($this->shift());
        }
        // MODIFIED
        return new static($this);
    }

    /**
     * {@inheritDoc}
     */
    public function set(int $index, $value): SequenceInterface
    {
        $this->checkRange($index);
        $this->internal[$index] = $value;

        return $this;
    }

    /**
     * {@inheritDoc}
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
     */
    public function slice(int $offset, int $length = null): SequenceInterface
    {
        if (func_num_args() === 1) {
            $length = count($this);
        }

        return new static(array_slice($this->internal, $offset, $length));
    }

    /**
     * {@inheritDoc}
     */
    public function sort(callable $comparator = null): SequenceInterface
    {
        $internal = $this->internal;

        if ($comparator) {
            usort($internal, $comparator);
        } else {
            sort($internal);
        }

        return new static($internal);
    }

    /**
     * {@inheritDoc}
     */
    public function sum()
    {
        return array_sum($this->internal);
    }

    /**
     * {@inheritDoc}
     */
    public function unshift(...$values): SequenceInterface
    {
        if ($values) {
            array_unshift($this->internal, ...$values);
            if ($this instanceof CapacityInterface) {
                $this->adjustCapacity();
            }
        }

        return new static($this->internal);
    }

    /**
     * @return \Generator
     */
    public function getIterator()
    {
        foreach ($this->internal as $value) {
            yield $value;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function offsetSet($offset, $value)
    {
        if ($offset === null) {
            $this->push($value);
        } else {
            $this->set($offset, $value);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function &offsetGet($offset)
    {
        $this->checkRange($offset);

        return $this->internal[$offset];
    }

    /**
     * {@inheritDoc}
     */
    public function offsetUnset($offset)
    {
        // Unset should be quiet, so we shouldn't allow 'remove' to throw.
        if (is_int($offset) && $offset >= 0 && $offset < count($this)) {
            $this->remove($offset);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function offsetExists($offset)
    {
        if ($offset < 0 || $offset >= count($this)) {
            return false;
        }

        return $this->get($offset) !== null;
    }

    /**
     *
     *
     * @param int $index
     */
    protected function checkRange(int $index): void
    {
        if ($index < 0 || $index >= count($this->internal)) {
            throw new OutOfRangeException('Out of range');
        }
    }

    /**
     * @param int $rotations
     * @param int $count
     *
     * @return int
     */
    protected function normalizeRotations(int $rotations, int $count)
    {
        if ($rotations < 0) {
            return $count - (abs($rotations) % $count);
        }

        return $rotations % $count;
    }
}
