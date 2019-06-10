<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

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
     * Creates an instance using the values of an array or Traversable object.
     *
     * @param array|\Traversable $values
     */
    public function __construct($values = null)
    {
        $this->internal = new Vector($values ?? []);
    }

    /**
     * {@inheritDoc}
     */
    public function peek()
    {
        return $this->internal->last();
    }

    /**
     * {@inheritDoc}
     */
    public function pop()
    {
        return $this->internal->pop();
    }

    /**
     * {@inheritDoc}
     */
    public function push(...$values): StackInterface
    {
        $this->internal->push(...$values);

        return $this;
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
    public function count(): int
    {
        return count($this->internal);
    }

    /**
     * {@inheritDoc}
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
     * {@inheritDoc}
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
     * {@inheritDoc}
     *
     * @throws OutOfBoundsException
     */
    public function offsetSet($offset, $value)
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
    public function offsetGet($offset)
    {
        throw new Error('Not supported');
    }

    /**
     * {@inheritDoc}
     *
     * @throws Error
     */
    public function offsetUnset($offset)
    {
        throw new Error('Not supported');
    }

    /**
     * {@inheritDoc}
     *
     * @throws Error
     */
    public function offsetExists($offset)
    {
        throw new Error('Not supported');
    }
}
