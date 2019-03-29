<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Structure;

use Altair\Structure\Contracts\CapacityInterface;
use Altair\Structure\Contracts\VectorInterface;
use ArrayAccess;
use IteratorAggregate;

/**
 * Vector.
 *
 * A Vector is a Sequence of values in a contiguous buffer that grows and shrinks automatically. It’s the most efficient
 * sequential structure because a value’s index is a direct mapping to its index in the buffer, and the growth factor
 * isn't bound to a specific multiple or exponent.
 *
 * @link https://medium.com/@rtheunissen/efficient-data-structures-for-php-7-9dda7af674cd#.gl62k1xqr
 *
 */
class Vector implements IteratorAggregate, ArrayAccess, VectorInterface, CapacityInterface
{
    use Traits\SequenceTrait;
    use Traits\CapacityTrait;

    /**
     * Creates an instance using the values of an array or Traversable object.
     *
     * @param array|\Traversable|Contracts\CollectionInterface|null $values
     */
    public function __construct($values = null)
    {
        $this->capacity = VectorInterface::MIN_VECTOR_CAPACITY;

        if (func_num_args()) {
            $this->pushAll($this->normalizeItems(($values??[])));
        }
    }

    /**
     * Adjusts the structure's capacity according to its current size.
     */
    protected function adjustCapacity()
    {
        $size = count($this);

        // Automatically truncate the allocated buffer when the size of the
        // structure drops low enough.
        if ($size < $this->capacity / 4) {
            $this->capacity = max(VectorInterface::MIN_VECTOR_CAPACITY, $this->capacity / 2);
        } else {
            // Also check if we should increase capacity when the size changes.
            if ($size >= $this->capacity) {
                $this->increaseCapacity();
            }
        }
    }

    /**
     * Increase capacity.
     */
    protected function increaseCapacity()
    {
        $size = count($this);

        if ($size > $this->capacity) {
            $this->capacity = max(intval($this->capacity * 1.5), $size);
        }

        return $this;
    }
}
