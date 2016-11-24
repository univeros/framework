<?php

namespace Altair\Structure\Contracts;

interface StackInterface extends CollectionInterface
{
    /**
     * Returns the value at the top of the stack without removing it.
     *
     * @throws \UnderflowException if the stack is empty.
     *
     * @return mixed
     *
     */
    public function peek();

    /**
     * Returns and removes the value at the top of the stack.
     *
     * @throws \UnderflowException if the stack is empty.
     *
     * @return mixed
     *
     */
    public function pop();

    /**
     * Pushes zero or more values into the front of the queue.
     *
     * @param mixed ...$values
     *
     * @return StackInterface
     */
    public function push(...$values): StackInterface;
}
