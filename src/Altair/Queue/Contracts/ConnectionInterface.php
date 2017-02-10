<?php
namespace Altair\Queue\Contracts;

interface ConnectionInterface
{
    /**
     * Connects to the queue store.
     *
     * @return ConnectionInterface
     */
    public function connect(): ConnectionInterface;

    /**
     * Disconnects the queue store.
     *
     * @return bool
     */
    public function disconnect(): bool;

    /**
     * Returns the internal store connection instance.
     *
     * @return mixed
     */
    public function getInstance();
}
