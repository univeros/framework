<?php

namespace Altair\Queue\Contracts;

interface AdapterInterface
{
    /**
     * @return AdapterInterface
     */
    public function init(): AdapterInterface;

    /**
     * @return AdapterInterface
     */
    public function getConnection(): AdapterInterface;

    /**
     * @param JobInterface $job
     *
     * @return bool
     */
    public function enqueue(JobInterface $job): bool;

    /**
     * @return JobInterface
     */
    public function dequeue();

    /**
     * @param JobInterface $job
     */
    public function ack(JobInterface $job);

    /**
     * @return bool
     */
    public function isEmpty(): bool;
}
