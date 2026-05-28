<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Structure;

use Altair\Structure\Contracts\PriorityNodeInterface;
use OutOfBoundsException;

/**
 * PriorityNode
 *
 * A node which represents a value, priority and a stamp on a PriorityQueue
 *
 * @template TValue
 *
 * @implements PriorityNodeInterface<TValue>
 */
class PriorityNode implements PriorityNodeInterface
{
    /**
     * PriorityNode constructor.
     *
     * @param TValue $value
     */
    public function __construct(public mixed $value, public int $priority, public int $stamp)
    {
    }

    /**
     * Allows unset($node->value) to soft-null the payload rather than remove it.
     *
     * priority and stamp are required ordering keys (always int) and are not
     * accessible through this magic getter.
     */
    public function __get(string $name): mixed
    {
        if ($name === 'value') {
            $this->value = null;

            return null;
        }

        throw new OutOfBoundsException('Out of bounds');
    }
}
