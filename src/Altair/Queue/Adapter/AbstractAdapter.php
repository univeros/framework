<?php
namespace Altair\Queue\Adapter;

use Altair\Middleware\Contracts\PayloadInterface;
use Altair\Queue\Contracts\JobInterface;
use Altair\Queue\Contracts\QueueAdapterInterface;
use Altair\Queue\Contracts\QueueConnectionInterface;

abstract class AbstractAdapter implements QueueAdapterInterface
{
    /**
     * @var QueueConnectionInterface
     */
    protected $connection;

    /**
     * @return QueueConnectionInterface
     */
    public function getConnection(): QueueConnectionInterface
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
        return $payload->getAttribute(JobInterface::ATTRIBUTE_QUEUE_NAME, QueueAdapterInterface::DEFAULT_QUEUE_NAME);
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
