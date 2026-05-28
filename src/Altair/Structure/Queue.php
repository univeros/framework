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
use Altair\Structure\Contracts\QueueInterface;
use Altair\Structure\Traits\CollectionTrait;
use ArrayAccess;
use Error;
use Generator;
use IteratorAggregate;
use OutOfBoundsException;
use Override;
use ReturnTypeWillChange;
use Traversable;

/**
 * Queue.
 *
 * A Queue is a “first in, first out” or “FIFO” structure that only allows access to the value at the front of the queue
 * and iterates in that order, destructively.
 *
 * @link https://medium.com/@rtheunissen/efficient-data-structures-for-php-7-9dda7af674cd#.gl62k1xqr
 *
 * @template TValue
 *
 * @implements QueueInterface<TValue>
 * @implements IteratorAggregate<int, TValue>
 * @implements ArrayAccess<int, TValue>
 *
 * @phpstan-consistent-constructor
 */
class Queue implements IteratorAggregate, ArrayAccess, QueueInterface, CapacityInterface
{
    /** @use CollectionTrait<int, TValue> */
    use CollectionTrait;

    /**
     * Backed by a Deque. The empty-array default is required for trait property
     * compatibility (CollectionTrait declares `$internal = []`) and is replaced
     * with a Deque in the constructor before any method is invoked.
     *
     * @var Deque<TValue>
     */
    protected $internal = [];

    /**
     * Creates an instance using the values of an array or Traversable object.
     *
     * @param array<array-key, TValue>|Traversable<array-key, TValue>|Queue<TValue>|null $values
     */
    public function __construct($values = null)
    {
        $this->internal = new Deque($values ?? []);
    }

    /**
     * {@inheritDoc}
     *
     * @return TValue
     */
    #[Override]
    public function peek()
    {
        return $this->internal->first();
    }

    /**
     * {@inheritDoc}
     *
     * @return TValue
     */
    #[Override]
    public function pop()
    {
        return $this->internal->shift();
    }

    /**
     * {@inheritDoc}
     *
     * @param TValue ...$values
     *
     * @return QueueInterface<TValue>
     */
    #[Override]
    public function push(...$values): QueueInterface
    {
        $this->internal->push(...$values);

        return $this;
    }

    /**
     * {@inheritDoc}
     *
     * @return QueueInterface<TValue>
     */
    #[Override]
    public function allocate(int $capacity): QueueInterface
    {
        $this->internal->allocate($capacity);

        return $this;
    }

    /**
     * Returns the current capacity of the queue.
     */
    #[Override]
    public function capacity(): int
    {
        return $this->internal->capacity();
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function copy(): static
    {
        return new static($this->internal);
    }

    /**
     * {@inheritDoc}
     *
     * @return array<array-key, TValue>
     */
    #[Override]
    public function toArray(): array
    {
        return $this->internal->toArray();
    }

    /**
     * Get iterator.
     *
     * @return Generator<int, TValue>
     */
    #[ReturnTypeWillChange]
    #[Override]
    public function getIterator()
    {
        while (!$this->isEmpty()) {
            yield $this->pop();
        }
    }

    /**
     * {@inheritDoc}
     *
     * @param TValue $value
     *
     * @throws OutOfBoundsException
     */
    #[ReturnTypeWillChange]
    #[Override]
    public function offsetSet($offset, mixed $value): void
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
    #[ReturnTypeWillChange]
    #[Override]
    public function offsetGet($offset): void
    {
        throw new Error('Not supported');
    }

    /**
     * {@inheritDoc}
     *
     * @throws Error
     */
    #[ReturnTypeWillChange]
    #[Override]
    public function offsetUnset($offset): void
    {
        throw new Error('Not supported');
    }

    /**
     * {@inheritDoc}
     *
     * @throws Error
     */
    #[ReturnTypeWillChange]
    #[Override]
    public function offsetExists($offset): bool
    {
        throw new Error('Not supported');
    }
}
