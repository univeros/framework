<?php
namespace Altair\Queue;

use Altair\Happen\Event;
use Altair\Happen\EventDispatcherInterface;
use Altair\Middleware\Contracts\PayloadInterface;
use Altair\Queue\Contracts\JobInterface;
use Altair\Queue\Contracts\JobProcessorInterface;
use Altair\Queue\Contracts\QueueManagerInterface;
use Altair\Queue\Contracts\WorkerInterface;
use Altair\Queue\Support\WorkerOptions;
use Psr\Cache\CacheItemPoolInterface;

// TODO: Discuss with devops whether would be much better to use a simpler script and daemonize it instead.
// This class is the Laravel's interpretation of queue workers.
// PROS: Events
// CONS: Over complicating simple stuff?
//
// Thank you Laravel.
class Worker implements WorkerInterface
{
    protected $manager;
    protected $cache;
    protected $processor;
    protected $events;
    protected $paused;
    protected $quit = false;

    /**
     * Worker constructor.
     *
     * @param QueueManagerInterface $manager
     * @param JobProcessorInterface $processor
     * @param EventDispatcherInterface $events
     * @param CacheItemPoolInterface $cache
     */
    public function __construct(
        QueueManagerInterface $manager,
        JobProcessorInterface $processor,
        EventDispatcherInterface $events,
        CacheItemPoolInterface $cache
    ) {
        $this->manager = $manager;
        $this->processor = $processor;
        $this->events = $events;
        $this->cache = $cache;
    }

    /**
     * Starts listening a queue (configured on the worker options).
     *
     * @param WorkerOptions $options
     */
    public function run(WorkerOptions $options)
    {
        $this->listenForSignals();

        $this->events->dispatch(WorkerInterface::STARTING_EVENT);

        $lastRestart = $this->getTimestampOfLastQueueRestart();

        while (true) {
            if (!$this->shouldRun()) {
                $this->pauseWorker($options, $lastRestart);
                continue;
            }

            $job = $this->manager->pop($options->getQueue());

            if ($job instanceof PayloadInterface) {
                $this->registerTimeoutHandler($job, $options);
                $this->fireJobEvent(WorkerInterface::JOB_PROCESS_EVENT, $job);

                // The job processor is the one that knows how to handle that specific job from the queue
                // it should mark the job with failures (if any) and user should do whatever with it when
                // that specific event happens -ie logging.
                $this->processor->process($job->withAttribute(WorkerInterface::WORKER_OPTIONS_ATTRIBUTE, $options));

                if (($error = $job->getAttribute(JobInterface::ATTRIBUTE_FAILURE)) !== null) {
                    $this->fireJobEvent(WorkerInterface::JOB_FAILED_EVENT, $job);
                } else {
                    $this->fireJobEvent(WorkerInterface::JOB_PROCESSED_EVENT, $job);
                }
            } else {
                $this->pause($options->getSleep());
            }

            $this->stopIfNecessary($options, $lastRestart);
        }
    }

    /**
     * Kill the process.
     *
     * @param  int $status
     *
     * @return void
     */
    public function kill($status = 0)
    {
        $this->events->dispatch(WorkerInterface::KILLED_EVENT);

        if (extension_loaded('posix')) {
            posix_kill(getmypid(), SIGKILL);
        }

        exit($status);
    }

    /**
     * Sleep the script for a given number of seconds.
     *
     * @param  int $seconds
     *
     * @return void
     */
    public function pause(int $seconds)
    {
        sleep($seconds);
    }

    /**
     * Stop listening and bail out of the script.
     *
     * @param  int $status
     *
     * @return void
     */
    public function stop($status = 0)
    {
        $this->events->dispatch(WorkerInterface::STOPPED_EVENT);

        exit($status);
    }

    /**
     * Determine if the memory limit has been exceeded.
     *
     * @param  int $memoryLimit
     *
     * @return bool
     */
    public function memoryExceeded(int $memoryLimit): bool
    {
        return (memory_get_usage() / 1024 / 1024) >= $memoryLimit;
    }

    /**
     * Raises an event
     *
     * @param string $name
     * @param PayloadInterface $payload
     */
    protected function fireJobEvent(string $name, PayloadInterface $payload)
    {
        $event = (new Event($name))->withArgument('job', $payload);

        $this->events->dispatch($name, $event);
    }

    /**
     * @return int|null
     */
    protected function getTimestampOfLastQueueRestart(): ?int
    {
        return $this->cache->getItem(WorkerInterface::QUEUE_RESTART_CACHE_KEY)->get();
    }

    /**
     * Determine if the queue worker should restart.
     *
     * @param $lastRestart
     *
     * @return bool
     */
    protected function shouldRestart($lastRestart): bool
    {
        return $this->getTimestampOfLastQueueRestart() !== $lastRestart;
    }

    /**
     * Register the worker timeout handler (PHP 7.1+).
     *
     * @param PayloadInterface $payload
     * @param WorkerOptions $options
     */
    protected function registerTimeoutHandler(PayloadInterface $payload, WorkerOptions $options)
    {
        if ($options->getTimeout() > 0 && $this->supportsAsyncSignals()) {
            // We will register a signal handler for the alarm signal so that we can kill this
            // process if it is running too long because it has frozen. This uses the async
            // signals supported in recent versions of PHP to accomplish it conveniently.
            pcntl_signal(
                SIGALRM,
                function () {
                    $this->kill(1);
                }
            );
            pcntl_alarm($this->getJobTimeout($payload, $options));
        }
    }

    /**
     * Get the appropriate timeout for the given job.
     *
     * @param PayloadInterface $payload
     * @param WorkerOptions $options
     *
     * @return int
     */
    protected function getJobTimeout(PayloadInterface $payload, WorkerOptions $options): int
    {
        $timeout = $payload->getAttribute(JobInterface::ATTRIBUTE_TIMEOUT, 0);

        return $timeout + $options->getTimeout() + $options->getSleep();
    }

    /**
     * Pause the worker for the current loop.
     *
     * @param WorkerOptions $options
     * @param int|null $lastRestart
     */
    protected function pauseWorker(WorkerOptions $options, $lastRestart)
    {
        $sleep = $options->getSleep();
        $this->pause($sleep > 0 ? $sleep : 1);
        $this->stopIfNecessary($options, $lastRestart);
    }

    /**
     * Stop the process if necessary.
     *
     * @param WorkerOptions $options
     * @param $lastRestart
     */
    protected function stopIfNecessary(WorkerOptions $options, $lastRestart)
    {
        if ($this->quit) {
            $this->kill();
        }

        if ($this->memoryExceeded($options->getMemory())) {
            $this->stop(SIGUSR2);
        } elseif ($this->shouldRestart($lastRestart)) {
            $this->stop();
        }
    }

    /**
     * Returns if the worker daemon should process on this iteration.
     *
     * @return bool
     */
    protected function shouldRun(): bool
    {
        return $this->paused || $this->events->dispatch(WorkerInterface::RUNNING_EVENT) === true;
    }

    /**
     * Enable async signals for the process.
     *
     * @return void
     */
    protected function listenForSignals()
    {
        if ($this->supportsAsyncSignals()) {
            pcntl_async_signals(true); // ticks not needed
            pcntl_signal(
                SIGTERM,
                function () {
                    $this->quit = true;
                }
            );
            pcntl_signal(
                SIGUSR2,
                function () {
                    $this->paused = true;
                }
            );
            pcntl_signal(
                SIGCONT,
                function () {
                    $this->paused = false;
                }
            );

            $event = (new Event(WorkerInterface::LISTENING_EVENT))
                ->withArgument(WorkerInterface::WORKER_PID_ATTRIBUTE, getmypid());

            $this->events->dispatch(WorkerInterface::LISTENING_EVENT, $event);
        }
    }

    /**
     * Determine if "async" signals are supported.
     *
     * @return bool
     */
    protected function supportsAsyncSignals(): bool
    {
        return version_compare(PHP_VERSION, '7.1.0') >= 0 && extension_loaded('pcntl');
    }
}
