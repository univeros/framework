<?php
namespace Altair\Queue\Adapter;

use Altair\Queue\Contracts\ConnectionInterface;
use Altair\Queue\Contracts\JobInterface;
use Altair\Queue\Contracts\QueueAdapterInterface;

abstract class AbstractAdapter implements QueueAdapterInterface
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

    protected function createPayload(JobInterface $job, array $data = []): string
    {
        $payload = [
            'id' => $job->is
        ];
    }

}
