<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

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
     * {@inheritDoc}
     */
    public function peek()
    {
        return $this->internal->first();
    }

    /**
     * {@inheritDoc}
     */
    public function pop()
    {
        return $this->internal->shift();
    }

    /**
     * {@inheritDoc}
     */
    public function push(...$values): QueueInterface
    {
        $this->internal->push(...$values);

        return $this;
    }

    /**
     * {@inheritDoc}
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
     * {@inheritDoc}
     */
    public function copy()
    {
        return new static($this->internal);
    }

    /**
     * {@inheritDoc}
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
     * {@inheritDoc}
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
     * {@inheritDoc}
     *
     * @throws Error
     */
    public function offsetGet($offset)
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

    /**
     * {@inheritDoc}
     *
     * @throws Error
     */
    public function offsetExists($offset)
    {
        throw new Error();
    }
}
