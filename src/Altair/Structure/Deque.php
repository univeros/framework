<?php

namespace Altair\Structure;

use Altair\Structure\Contracts\CapacityInterface;
use Altair\Structure\Contracts\SequenceInterface;
use ArrayAccess;
use IteratorAggregate;

/**
 * Deque.
 *
 * A Deque ("deck") is a Sequence of values in a contiguous buffer that grows and shrinks automatically. The name is a
 * common abbreviation of "double-ended queue" and is used internally by `Altair\Structure\Queue`.
 *
 * Two pointers are used to keep track of a head and a tail. The pointers can "wrap around" the end of the buffer, which
 * avoids to the need to move other values around to make room.
 *
 * Accessing a value by index requires a translation between the index and its corresponding position in the buffer:
 * ((head + position) % capacity).
 *
 * @link https://medium.com/@rtheunissen/efficient-data-structures-for-php-7-9dda7af674cd#.gl62k1xqr
 */
class Deque implements IteratorAggregate, ArrayAccess, SequenceInterface, CapacityInterface
{
    use Traits\SequenceTrait;
    use Traits\SquaredCapacityTrait;

    /**
     * @inheritdoc
     */
    public function __construct($values = null)
    {
        $this->pushAll($this->normalizeItems($values ?? []));
    }
}
