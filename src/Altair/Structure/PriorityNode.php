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
    public function __construct(public mixed $value, public int $priority, public int $stamp) {}

    /**
     * Resolves reads of $value after it has been unset, returning null rather
     * than triggering an "undefined property" error. The property is not
     * re-initialised, so its declared TValue type is never violated; every
     * subsequent read routes back through this accessor and yields null.
     *
     * priority and stamp are required ordering keys (always int) and are not
     * accessible through this magic getter.
     */
    public function __get(string $name): mixed
    {
        if ($name === 'value') {
            return null;
        }

        throw new OutOfBoundsException('Out of bounds');
    }
}
