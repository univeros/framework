<?php
namespace Altair\Queue\Contracts;

interface WorkerInterface
{
    const QUEUE_RESTART_CACHE_KEY = 'altair:queue:restart';
    const WORKER_OPTIONS_ATTRIBUTE = 'altair:worker:options';
    const WORKER_PID_ATTRIBUTE = 'altair:worker:pid';
    const JOB_PROCESSED_EVENT = 'altair:OnQueueJobProcessed';
    const JOB_PROCESS_EVENT = 'altair:OnQueueJob';
    const JOB_FAILED_EVENT = 'altair:OnQueueJobFailed';
    const STARTING_EVENT = 'altair:OnQueueWorkerStarted';
    const RUNNING_EVENT = 'altair:OnQueueWorkerRunning';
    const STOPPED_EVENT = 'altair:OnQueueWorkerStopped';
    const LISTENING_EVENT = 'altair:OnQueueWorkerListening';
    const KILLED_EVENT = 'altair:OnQueueWorkerKilled';
}
