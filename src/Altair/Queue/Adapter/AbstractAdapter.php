<?php
namespace Altair\Queue\Adapter;

use Altair\Middleware\Contracts\PayloadInterface;
use Altair\Queue\Contracts\AdapterInterface;
use Altair\Queue\Contracts\ConnectionInterface;
use Altair\Queue\Contracts\JobInterface;

abstract class AbstractAdapter implements AdapterInterface
{
    /**
     * @var ConnectionInterface
     */
    protected $connection;

    /**
     * @return ConnectionInterface
     */
    public function getConnection(): ConnectionInterface
    {
        return $this->connection;
    }

    /**
     * @param PayloadInterface $payload
     *
     * @return string
     */
    protected function getQueueNameFromAttribute(PayloadInterface $payload): string
    {
        return $payload->getAttribute(JobInterface::ATTRIBUTE_QUEUE_NAME, AdapterInterface::DEFAULT_QUEUE_NAME);
    }

    /**
     * Checkes whether the payload has an "id" set or not.
     *
     * @param PayloadInterface $payload
     *
     * @return bool
     */
    protected function hasIdAttribute(PayloadInterface $payload): bool
    {
        return $payload->getAttribute(JobInterface::ATTRIBUTE_ID) !== null;
    }
}
