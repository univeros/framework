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
use Altair\Structure\Contracts\StackInterface;
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
 * Stack.
 *
 * A Stack is a “last in, first out” or “LIFO” structure that only allows access to the value at the top of the
 * structure and iterates in that order, destructively. Altair\Structure\Stack uses a Altair\Structure\Vector
 * internally.
 *
 * @link https://medium.com/@rtheunissen/efficient-data-structures-for-php-7-9dda7af674cd#.gl62k1xqr
 *
 * @template TValue
 *
 * @implements StackInterface<TValue>
 * @implements IteratorAggregate<int, TValue>
 * @implements ArrayAccess<int, TValue>
 *
 * @phpstan-consistent-constructor
 */
class Stack implements IteratorAggregate, ArrayAccess, StackInterface, CapacityInterface
{
    /** @use CollectionTrait<int, TValue> */
    use CollectionTrait;

    /**
     * Backed by a Vector. The empty-array default is required for trait property
     * compatibility (CollectionTrait declares `$internal = []`) and is replaced
     * with a Vector in the constructor before any method is invoked.
     *
     * @var Vector<TValue>
     */
    protected $internal = [];

    /**
     * Creates an instance using the values of an array or Traversable object.
     *
     * @param array<array-key, TValue>|Traversable<array-key, TValue>|null $values
     */
    public function __construct($values = null)
    {
        $this->internal = new Vector($values ?? []);
    }

    /**
     * {@inheritDoc}
     *
     * @return TValue
     */
    #[Override]
    public function peek(): mixed
    {
        return $this->internal->last();
    }

    /**
     * {@inheritDoc}
     *
     * @return TValue
     */
    #[Override]
    public function pop()
    {
        return $this->internal->pop();
    }

    /**
     * {@inheritDoc}
     *
     * @param TValue ...$values
     *
     * @return StackInterface<TValue>
     */
    #[Override]
    public function push(...$values): StackInterface
    {
        $this->internal->push(...$values);

        return $this;
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
     */
    #[ReturnTypeWillChange]
    #[Override]
    public function count(): int
    {
        return \count($this->internal);
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function allocate(int $capacity): static
    {
        $this->internal->allocate($capacity);

        return $this;
    }

    /**
     * Returns the current capacity of the stack.
     */
    #[Override]
    public function capacity(): int
    {
        return $this->internal->capacity();
    }

    /**
     * {@inheritDoc}
     *
     * @return array<array-key, TValue>
     */
    #[Override]
    public function toArray(): array
    {
        return array_reverse($this->internal->toArray());
    }

    /**
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
