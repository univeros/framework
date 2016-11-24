<?php

namespace Altair\Structure;

use Altair\Structure\Contracts\CapacityInterface;
use Altair\Structure\Contracts\QueueInterface;
use ArrayAccess;
use Error;
use IteratorAggregate;
use OutOfBoundsException;

/**
 * Queue.
 *
 * A Queue is a “first in, first out” or “FIFO” structure that only allows access to the value at the front of the queue
 * and iterates in that order, destructively.
 *
 * @link https://medium.com/@rtheunissen/efficient-data-structures-for-php-7-9dda7af674cd#.gl62k1xqr
 */
class Queue implements IteratorAggregate, ArrayAccess, QueueInterface, CapacityInterface
{
    use Traits\CollectionTrait;

    /**
     * Creates an instance using the values of an array or Traversable object.
     *
     * @param array|\Traversable|Queue $values
     */
    public function __construct($values = null)
    {
        $this->internal = new Deque($values ?? []);
    }

    /**
     * {@inheritdoc}
     */
    public function peek()
    {
        return $this->internal->first();
    }

    /**
     * {@inheritdoc}
     */
    public function pop()
    {
        return $this->internal->shift();
    }

    /**
     * {@inheritdoc}
     */
    public function push(...$values): QueueInterface
    {
        $this->internal->push(...$values);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function allocate(int $capacity): QueueInterface
    {
        $this->internal->allocate($capacity);

        return $this;
    }

    /**
     * Returns the current capacity of the queue.
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
    public function copy()
    {
        return new static($this->internal);
    }

    /**
     * {@inheritdoc}
     */
    public function toArray(): array
    {
        return $this->internal->toArray();
    }

    /**
     * Get iterator.
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
