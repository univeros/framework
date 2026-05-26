<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Structure;

use Altair\Structure\Traits\CollectionTrait;
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
    use CollectionTrait;

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
    #[\Override]
    public function peek()
    {
        return $this->internal->first();
    }

    /**
     * {@inheritDoc}
     */
    #[\Override]
    public function pop()
    {
        return $this->internal->shift();
    }

    /**
     * {@inheritDoc}
     */
    #[\Override]
    public function push(...$values): QueueInterface
    {
        $this->internal->push(...$values);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    #[\Override]
    public function allocate(int $capacity): QueueInterface
    {
        $this->internal->allocate($capacity);

        return $this;
    }

    /**
     * Returns the current capacity of the queue.
     */
    #[\Override]
    public function capacity(): int
    {
        return $this->internal->capacity();
    }

    /**
     * {@inheritDoc}
     */
    #[\Override]
    public function copy(): static
    {
        return new static($this->internal);
    }

    /**
     * {@inheritDoc}
     */
    #[\Override]
    public function toArray(): array
    {
        return $this->internal->toArray();
    }

    /**
     * Get iterator.
     */
    #[\ReturnTypeWillChange]
    #[\Override]
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
    #[\ReturnTypeWillChange]
    #[\Override]
    public function offsetSet($offset, $value): void
    {
        if ($offset === null) {
            $this->push($value);
        } else {
            throw new OutOfBoundsException('Out of bounds');
        }
    }

    /**
     * {@inheritDoc}
     *
     * @throws Error
     */
    #[\ReturnTypeWillChange]
    #[\Override]
    public function offsetGet($offset)
    {
        throw new Error('Not supported');
    }

    /**
     * {@inheritDoc}
     *
     * @throws Error
     */
    #[\ReturnTypeWillChange]
    #[\Override]
    public function offsetUnset($offset)
    {
        throw new Error('Not supported');
    }

    /**
     * {@inheritDoc}
     *
     * @throws Error
     */
    #[\ReturnTypeWillChange]
    #[\Override]
    public function offsetExists($offset)
    {
        throw new Error('Not supported');
    }
}
