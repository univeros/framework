<?php

namespace Altair\Queue\Contracts;

interface QueueAdapterInterface
{
    /**
     * @param JobInterface $job
     * @param array $data
     * @param string|null $queue
     *
     * @return bool
     */
    public function push(JobInterface $job, array $data = [], string $queue = null): bool;

    /**
     * @param string|null $queue
     *
     * @return JobInterface
     */
    public function pop(string $queue = null): JobInterface;

    /**
     * @param string|null $queue
     *
     * @param JobInterface $job
     */
    public function ack(JobInterface $job, string $queue = null);

    /**
     * @param string|null $queue
     *
     * @return bool
     */
    public function isEmpty(string $queue = null): bool;

    /**
     * @return ConnectionInterface
     */
    public function getConnection(): ConnectionInterface;
}
