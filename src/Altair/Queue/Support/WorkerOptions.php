<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Queue\Support;

use Altair\Queue\Contracts\QueueAdapterInterface;

class WorkerOptions
{
    protected $delay;
    protected $memory;
    protected $timeout;
    protected $sleep;
    protected $maxTries;
    protected $force;

    /**
     * Create a new worker options instance.
     *
     * @param string $queue
     * @param int $delay
     * @param int $memory
     * @param int $timeout
     * @param int $sleep
     * @param int $maxTries
     * @param bool $force
     */
    public function __construct(
        string $queue = null,
        int $delay = 0,
        int $memory = 128,
        int $timeout = 60,
        int $sleep = 3,
        int $maxTries = 0,
        bool $force = false
    ) {
        $this->queue = $queue;
        $this->delay = $delay;
        $this->sleep = $sleep;
        $this->force = $force;
        $this->memory = $memory;
        $this->timeout = $timeout;
        $this->maxTries = $maxTries;
    }

    /**
     * The queue name. If none specified, defaults to `QueueAdapterInterface::DEFAULT_QUEUE_NAME`.
     *
     * @return string
     */
    public function getQueue(): string
    {
        return $this->queue?? QueueAdapterInterface::DEFAULT_QUEUE_NAME;
    }

    /**
     * The number of seconds before a released job will be available.
     *
     * @return int
     */
    public function getDelay(): int
    {
        return $this->delay;
    }

    /**
     * The maximum amount of RAM the worker may consume.
     *
     * @return int
     */
    public function getMemory(): int
    {
        return $this->memory;
    }

    /**
     * The maximum number of seconds a child worker may run.
     *
     * @return int
     */
    public function getTimeout(): int
    {
        return $this->timeout;
    }

    /**
     * The number of seconds to wait in between polling the queue.
     *
     * @return int
     */
    public function getSleep(): int
    {
        return $this->sleep;
    }

    /**
     * The maximum amount of times a job may be attempted.
     *
     * @return int
     */
    public function getMaxTries(): int
    {
        return $this->maxTries;
    }

    /**
     * Indicates if the worker should run in maintenance mode.
     *
     * @return bool
     */
    public function getForce(): bool
    {
        return $this->force;
    }
}
