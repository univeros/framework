<?php

namespace Altair\Queue\Contracts;

interface QueueAdapterInterface extends QueueInterface
{
    const DEFAULT_QUEUE_NAME = 'queue';

    /**
     * @return QueueConnectionInterface
     */
    public function getConnection(): QueueConnectionInterface;
}
