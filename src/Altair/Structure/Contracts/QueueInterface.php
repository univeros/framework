<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Structure\Contracts;

interface QueueInterface extends CollectionInterface
{
    /**
     * Returns the value at the front of the queue without removing it.
     *
     * @return mixed
     */
    public function peek();

    /**
     * Returns and removes the value at the front of the Queue.
     *
     * @return mixed
     */
    public function pop();

    /**
     * Pushes zero or more values into the front of the queue.
     *
     * @param mixed ...$values
     *
     * @return QueueInterface
     */
    public function push(...$values): QueueInterface;
}
