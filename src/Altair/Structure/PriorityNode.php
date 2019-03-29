<?php declare(strict_types=1);

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
 */
class PriorityNode implements PriorityNodeInterface
{
    /**
     * @var mixed
     */
    public $value;
    /**
     * @var int
     */
    public $priority;
    /**
     * @var int
     */
    public $stamp;

    /**
     * PriorityNode constructor.
     *
     * @param mixed $value
     * @param int $priority
     * @param int $stamp
     */
    public function __construct($value, int $priority, int $stamp)
    {
        $this->value = $value;
        $this->priority = $priority;
        $this->stamp = $stamp;
    }

    /**
     * This allows unset($pair->key) to not completely remove the property,
     * but be set to null instead.
     *
     * @param mixed $name
     *
     * @return mixed|null
     */
    public function __get($name)
    {
        if ($name === 'value' || $name === 'priority' || $name === 'stamp') {
            $this->$name = null;

            return;
        }
        throw new OutOfBoundsException();
    }
}
