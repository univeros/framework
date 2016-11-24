<?php

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
