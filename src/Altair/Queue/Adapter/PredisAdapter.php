<?php
namespace Altair\Queue\Adapter;

use Altair\Cache\Exception\InvalidMethodCallException;
use Altair\Middleware\Contracts\PayloadInterface;
use Altair\Middleware\Payload;
use Altair\Queue\Connection\PredisConnection;
use Altair\Queue\Contracts\JobInterface;
use Altair\Queue\Contracts\QueueAdapterInterface;
use Altair\Queue\Traits\EnsureIdAwareTrait;
use Predis\Transaction\MultiExec;

class PredisAdapter extends AbstractAdapter
{
    use EnsureIdAwareTrait;

    protected $expireTime;

    /**
     * RedisQueueStoreAdapter constructor.
     *
     * @param PredisConnection $connection
     * @param int $expireTime
     */
    public function __construct(PredisConnection $connection, $expireTime = 60)
    {
        $this->expireTime = $expireTime;
        $this->connection = $connection;
    }

    /**
     * @inheritdoc
     */
    public function push(PayloadInterface $payload): bool
    {
        $queue = $this->getQueueNameFromAttribute($payload);
        $delay = (int)$payload->getAttribute(JobInterface::ATTRIBUTE_DELAY, 0);
        $payload = json_encode($this->ensureId($payload)->withoutAttribute(JobInterface::ATTRIBUTE_JOB));

        return (bool) ($delay > time()
            ? $this->getConnection()->getInstance()->zadd($queue . ':delayed', $delay, $payload)
            : $this->getConnection()->getInstance()->rpush($queue, $payload));
    }

    /**
     * @inheritdoc
     */
    public function pop(string $queue = null): ?PayloadInterface
    {
        $queue = $queue?? QueueAdapterInterface::DEFAULT_QUEUE_NAME;
        $this->migrateExpiredJobs($queue);

        $job = $this->getConnection()->getInstance()->lpop($queue);

        if (null !== $job) {
            $this->getConnection()
                ->getInstance()
                ->zadd($queue . ':reserved', time() + $this->expireTime, $job);

            $data = json_decode($job, true);

            return (new Payload($data))
                ->withAttribute(JobInterface::ATTRIBUTE_QUEUE_NAME, $queue)
                ->withAttribute(JobInterface::ATTRIBUTE_JOB, $job);
        }

        return null;
    }

    /**
     * @inheritdoc
     */
    public function ack(PayloadInterface $payload)
    {
        if ($this->hasIdAttribute($payload)) {
            throw new InvalidMethodCallException('Job must have an id to be updated on queue.');
        }
        $queue = $this->getQueueNameFromAttribute($payload);

        $this->removeReserved($queue, $payload);

        if (!$payload->getAttribute(JobInterface::ATTRIBUTE_COMPLETED) === true) {
            $delay = $payload->getAttribute(JobInterface::ATTRIBUTE_DELAY);
            $now = time();

            if (null === $delay || $delay < $now) {
                $delay = $now + $this->expireTime;
            }
            $this->push($payload->withAttribute(JobInterface::ATTRIBUTE_DELAY, $delay));
        }
    }

    /**
     * @inheritdoc
     */
    public function isEmpty(string $queue = null): bool
    {
        return $this->getConnection()->getInstance()->llen($queue?? QueueAdapterInterface::DEFAULT_QUEUE_NAME) === 0;
    }

    /**
     * @param string $queue
     * @param PayloadInterface $payload
     */
    protected function removeReserved(string $queue, PayloadInterface $payload)
    {
        $this->getConnection()->getInstance()->zrem($queue . ':reserved', json_encode($payload));
    }

    /**
     * Migrates all expired jobs from delayed and reserved queues to the main queue to be processed.
     *
     * @param string $queue
     */
    protected function migrateExpiredJobs(string $queue)
    {
        $this->migrateJobs($queue . ':delayed', $queue);
        $this->migrateJobs($queue . ':reserved', $queue);
    }

    /**
     * Migrates expired jobs from one queue to another.
     *
     * @param string $from the name of the source queue
     * @param string $to the name of the target queue
     */
    protected function migrateJobs(string $from, string $to)
    {
        $options = ['cas' => true, 'watch' => $from, 'retry' => 10];
        $this->getConnection()->getInstance()->transaction(
            $options,
            function ($transaction) use ($from, $to) {
                $time = time();
                // First we need to get all of jobs that have expired based on the current time
                // so that we can push them onto the main queue. After we get them we simply
                // remove them from this "delay" queues. All of this within a transaction.
                $jobs = $this->getExpiredJobs($transaction, $from, $time);
                // If we actually found any jobs, we will remove them from the old queue and we
                // will insert them onto the new (ready) "queue". This means they will stand
                // ready to be processed by the queue worker whenever their turn comes up.
                if (count($jobs) > 0) {
                    $this->removeExpiredJobs($transaction, $from, $time);
                    $this->pushExpiredJobsOntoNewQueue($transaction, $to, $jobs);
                }
            }
        );
    }

    /**
     * Get the expired jobs from a given queue.
     *
     * @param  MultiExec $transaction
     * @param  string $from
     * @param  int $time
     *
     * @return array
     */
    protected function getExpiredJobs(MultiExec $transaction, string $from, int $time): array
    {
        // https://redis.io/commands/zrangebyscore it returns an array (wrong phpDoc):
        // list of elements in the specified score range (optionally with their scores).
        return $transaction->zrangebyscore($from, '-inf', $time);
    }

    /**
     * Remove the expired jobs from a given queue.
     *
     * @param  MultiExec $transaction
     * @param  string $from
     * @param  int $time
     *
     */
    protected function removeExpiredJobs(MultiExec $transaction, string $from, int $time)
    {
        $transaction->multi();
        $transaction->zremrangebyscore($from, '-inf', $time);
    }

    /**
     * Push all of the given jobs onto another queue.
     *
     * @param  \Predis\Transaction\MultiExec $transaction
     * @param  string $to
     * @param  array $jobs
     *
     */
    protected function pushExpiredJobsOntoNewQueue($transaction, $to, $jobs)
    {
        call_user_func_array([$transaction, 'rpush'], array_merge([$to], $jobs));
    }
}
