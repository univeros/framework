<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Queue\Contracts;

interface WorkerInterface
{
    public const QUEUE_RESTART_CACHE_KEY = 'altair:queue:restart';
    public const WORKER_OPTIONS_ATTRIBUTE = 'altair:worker:options';
    public const WORKER_PID_ATTRIBUTE = 'altair:worker:pid';
    public const JOB_PROCESSED_EVENT = 'altair:OnQueueJobProcessed';
    public const JOB_PROCESS_EVENT = 'altair:OnQueueJob';
    public const JOB_FAILED_EVENT = 'altair:OnQueueJobFailed';
    public const STARTING_EVENT = 'altair:OnQueueWorkerStarted';
    public const RUNNING_EVENT = 'altair:OnQueueWorkerRunning';
    public const STOPPED_EVENT = 'altair:OnQueueWorkerStopped';
    public const LISTENING_EVENT = 'altair:OnQueueWorkerListening';
    public const KILLED_EVENT = 'altair:OnQueueWorkerKilled';
}
