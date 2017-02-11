<?php

namespace Altair\Queue\Contracts;

interface AdapterInterface extends QueueInterface
{
    const DEFAULT_QUEUE_NAME = 'queue';

    /**
     * @return ConnectionInterface
     */
    public function getConnection(): ConnectionInterface;
}
