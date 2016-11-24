<?php

namespace Altair\Structure;

use Altair\Structure\Contracts\CapacityInterface;
use Altair\Structure\Contracts\StackInterface;
use ArrayAccess;
use Error;
use IteratorAggregate;
use OutOfBoundsException;

/**
 * Stack.
 *
 * A Stack is a “last in, first out” or “LIFO” structure that only allows access to the value at the top of the
 * structure and iterates in that order, destructively. Altair\Structure\Stack uses a Altair\Structure\Vector
 * internally.
 *
 * @link https://medium.com/@rtheunissen/efficient-data-structures-for-php-7-9dda7af674cd#.gl62k1xqr
 */
class Stack implements IteratorAggregate, ArrayAccess, StackInterface, CapacityInterface
{
    use Traits\CollectionTrait;

    /**
     * @var Vector
     */
    protected $internal;

    /**
     * Creates an instance using the values of an array or Traversable object.
     *
     * @param array|\Traversable $values
     */
    public function __construct($values = null)
    {
        $this->internal = new Vector($values ?? []);
    }

    /**
     * {@inheritdoc}
     */
    public function peek()
    {
        return $this->internal->last();
    }

    /**
     * {@inheritdoc}
     */
    public function pop()
    {
        return $this->internal->pop();
    }

    /**
     * {@inheritdoc}
     */
    public function push(...$values): StackInterface
    {
        $this->internal->push(...$values);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function copy()
    {
        return new static($this->internal);
    }

    /**
     * {@inheritdoc}
     */
    public function count(): int
    {
        return count($this->internal);
    }

    /**
     * {@inheritdoc}
     */
    public function allocate(int $capacity)
    {
        $this->internal->allocate($capacity);

        return $this;
    }

    /**
     * Returns the current capacity of the stack.
     *
     * @return int
     */
    public function capacity(): int
    {
        return $this->internal->capacity();
    }

    /**
     * {@inheritdoc}
     */
    public function toArray(): array
    {
        return array_reverse($this->internal->toArray());
    }

    /**
     * @return \Generator
     */
    public function getIterator()
    {
        while (!$this->isEmpty()) {
            yield $this->pop();
        }
    }

    /**
     * {@inheritdoc}
     *
     * @throws OutOfBoundsException
     */
    public function offsetSet($offset, $value)
    {
        if ($offset === null) {
            $this->push($value);
        } else {
            throw new OutOfBoundsException();
        }
    }

    /**
     * {@inheritdoc}
     *
     * @throws Error
     */
    public function offsetGet($offset)
    {
        throw new Error();
    }

    /**
     * {@inheritdoc}
     *
     * @throws Error
     */
    public function offsetUnset($offset)
    {
        throw new Error();
    }

    /**
     * {@inheritdoc}
     *
     * @throws Error
     */
    public function offsetExists($offset)
    {
        throw new Error();
    }
}
