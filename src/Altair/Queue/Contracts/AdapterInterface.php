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
     * @param JobInterface $mailJob
     *
     * @return bool
     */
    public function enqueue(JobInterface $mailJob): bool;

    /**
     * @return JobInterface
     */
    public function dequeue();

    /**
     * @param JobInterface $mailJob
     */
    public function ack(JobInterface $mailJob);

    /**
     * @return bool
     */
    public function isEmpty(): bool;
}
