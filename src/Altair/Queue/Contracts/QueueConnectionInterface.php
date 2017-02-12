<?php
namespace Altair\Queue\Contracts;

interface QueueConnectionInterface
{
    /**
     * Connects to the queue store.
     *
     * @return QueueConnectionInterface
     */
    public function connect(): QueueConnectionInterface;

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
