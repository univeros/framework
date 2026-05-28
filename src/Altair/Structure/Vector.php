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
use Altair\Structure\Contracts\VectorInterface;
use Altair\Structure\Traits\CapacityTrait;
use Altair\Structure\Traits\SequenceTrait;
use ArrayAccess;
use IteratorAggregate;
use Override;
use Traversable;

/**
 * Vector.
 *
 * A Vector is a Sequence of values in a contiguous buffer that grows and shrinks automatically. It’s the most efficient
 * sequential structure because a value’s index is a direct mapping to its index in the buffer, and the growth factor
 * isn't bound to a specific multiple or exponent.
 *
 * @link https://medium.com/@rtheunissen/efficient-data-structures-for-php-7-9dda7af674cd#.gl62k1xqr
 *
 * @template TValue
 *
 * @implements VectorInterface<TValue>
 * @implements IteratorAggregate<int, TValue>
 * @implements ArrayAccess<int, TValue>
 */
class Vector implements IteratorAggregate, ArrayAccess, VectorInterface, CapacityInterface
{
    /** @use SequenceTrait<TValue> */
    use SequenceTrait;
    use CapacityTrait;

    /**
     * Creates an instance using the values of an array or Traversable object.
     *
     * @param array<array-key, TValue>|Traversable<array-key, TValue>|Contracts\CollectionInterface<int, TValue>|null $values
     */
    public function __construct($values = null)
    {
        $this->capacity = VectorInterface::MIN_VECTOR_CAPACITY;

        if (\func_num_args() !== 0) {
            $this->pushAll($this->normalizeItems(($values ?? [])));
        }
    }

    /**
     * Adjusts the structure's capacity according to its current size.
     */
    protected function adjustCapacity(): void
    {
        $size = \count($this);

        // Automatically truncate the allocated buffer when the size of the
        // structure drops low enough.
        if ($size < $this->capacity / 4) {
            $this->capacity = max(VectorInterface::MIN_VECTOR_CAPACITY, intdiv($this->capacity, 2));
        } elseif ($size >= $this->capacity) {
            // Also check if we should increase capacity when the size changes.
            $this->increaseCapacity();
        }
    }

    /**
     * Increase capacity.
     */
    #[Override]
    protected function increaseCapacity(): static
    {
        $size = \count($this);

        if ($size > $this->capacity) {
            $this->capacity = max((int) ($this->capacity * 1.5), $size);
        }

        return $this;
    }
}
