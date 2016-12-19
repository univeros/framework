<?php

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
     * {@inheritdoc}
     */
    public function toArray(): array
    {
        return $this->internal;
    }

    /**
     * {@inheritdoc}
     */
    public function apply(callable $callback): SequenceInterface
    {
        foreach ($this->internal as &$value) {
            $value = $callback($value);
        }

        return new static($this->internal);
    }

    /**
     * {@inheritdoc}
     */
    public function merge($values): SequenceInterface
    {
        if (!is_array($values)) {
            $values = iterator_to_array($values);
        }

        return new static(array_merge($this->internal, $values));
    }

    /**
     * {@inheritdoc}
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
     * {@inheritdoc}
     */
    public function filter(callable $callback = null): SequenceInterface
    {
        return new static(array_filter($this->internal, $callback ?: 'boolval'));
    }

    /**
     * {@inheritdoc}
     */
    public function find($value)
    {
        return array_search($value, $this->internal, true);
    }

    /**
     * {@inheritdoc}
     */
    public function first()
    {
        if (empty($this->internal)) {
            throw new UnderflowException();
        }

        return $this->internal[0];
    }

    /**
     * {@inheritdoc}
     */
    public function get(int $index)
    {
        $this->checkRange($index);

        return $this->internal[$index];
    }

    /**
     * {@inheritdoc}
     */
    public function insert(int $index, ...$values): SequenceInterface
    {
        if ($index < 0 || $index > count($this->internal)) {
            throw new OutOfRangeException();
        }

        return new static(array_splice($this->internal, $index, 0, $values));
    }

    /**
     * {@inheritdoc}
     */
    public function join(string $glue = null): string
    {
        return implode($glue, $this->internal);
    }

    /**
     * {@inheritdoc}
     */
    public function last()
    {
        if ($this->isEmpty()) {
            throw new UnderflowException();
        }

        return end($this->internal);
    }

    /**
     * {@inheritdoc}
     */
    public function map(callable $callback): SequenceInterface
    {
        return new static(array_map($callback, $this->internal));
    }

    /**
     * {@inheritdoc}
     */
    public function pop()
    {
        if ($this->isEmpty()) {
            throw new UnderflowException();
        }

        $value = array_pop($this->internal);
        if (method_exists($this, 'adjustCapacity')) {
            $this->adjustCapacity();
        }

        return $value;
    }

    /**
     * {@inheritdoc}
     */
    public function push(...$values): SequenceInterface
    {
        $this->pushAll($values);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function reduce(callable $callback, $initial = null)
    {
        return array_reduce($this->internal, $callback, $initial);
    }

    /**
     * {@inheritdoc}
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
     * {@inheritdoc}
     */
    public function reverse(): SequenceInterface
    {
        return new static(array_reverse($this->internal));
    }

    /**
     * {@inheritdoc}
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
     * {@inheritdoc}
     */
    public function set(int $index, $value): SequenceInterface
    {
        $this->checkRange($index);
        $this->internal[$index] = $value;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function shift()
    {
        if ($this->isEmpty()) {
            throw new UnderflowException();
        }

        $value = array_shift($this->internal);
        if (method_exists($this, 'adjustCapacity')) {
            $this->adjustCapacity();
        }

        return $value;
    }

    /**
     * {@inheritdoc}
     */
    public function slice(int $offset, int $length = null): SequenceInterface
    {
        if (func_num_args() === 1) {
            $length = count($this);
        }

        return new static(array_slice($this->internal, $offset, $length));
    }

    /**
     * {@inheritdoc}
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
     * {@inheritdoc}
     */
    public function sum()
    {
        return array_sum($this->internal);
    }

    /**
     * {@inheritdoc}
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
     * {@inheritdoc}
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
     * {@inheritdoc}
     */
    public function &offsetGet($offset)
    {
        $this->checkRange($offset);

        return $this->internal[$offset];
    }

    /**
     * {@inheritdoc}
     */
    public function offsetUnset($offset)
    {
        // Unset should be quiet, so we shouldn't allow 'remove' to throw.
        if (is_integer($offset) && $offset >= 0 && $offset < count($this)) {
            $this->remove($offset);
        }
    }

    /**
     * {@inheritdoc}
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
    protected function checkRange(int $index)
    {
        if ($index < 0 || $index >= count($this->internal)) {
            throw new OutOfRangeException();
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
