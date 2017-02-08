<?php
namespace Altair\Queue\Adapter;

use Altair\Cache\Exception\InvalidMethodCallException;
use Altair\Middleware\Contracts\PayloadInterface;
use Altair\Middleware\Payload;
use Altair\Queue\Connection\BeanstalkdConnection;
use Altair\Queue\Contracts\AdapterInterface;
use Altair\Queue\Contracts\JobInterface;
use Pheanstalk\Job;
use Pheanstalk\Pheanstalk;
use Pheanstalk\PheanstalkInterface;

class BeanstalkdAdapter extends AbstractAdapter
{
    /**
     * @var int
     */
    protected $timeToRun;
    /**
     * @var int
     */
    protected $reserveTimeout;

    /**
     * BeanstalkdAdapter constructor.
     *
     * @param BeanstalkdConnection $connection
     * @param int $timeToRun
     * @param int $reserveTimeout
     */
    public function __construct(
        BeanstalkdConnection $connection,
        int $timeToRun = Pheanstalk::DEFAULT_TTR,
        int $reserveTimeout = 5
    ) {
        $this->connection = $connection->connect();
        $this->timeToRun = $timeToRun;
        $this->reserveTimeout = $reserveTimeout;
    }

    /**
     * @inheritdoc
     */
    public function push(PayloadInterface $payload): bool
    {
        $queue = $payload->getAttribute(JobInterface::ATTRIBUTE_QUEUE_NAME, AdapterInterface::DEFAULT_QUEUE_NAME);
        $delay = (int)$payload->getAttribute(JobInterface::ATTRIBUTE_DELAY, Pheanstalk::DEFAULT_DELAY);
        $delay = (int)max(Pheanstalk::DEFAULT_DELAY, $delay - time());

        return $this->getConnection()
            ->getInstance()
            ->useTube($queue)
            ->put(json_encode($payload), Pheanstalk::DEFAULT_PRIORITY, $delay, $this->timeToRun);
    }

    /**
     * @inheritdoc
     */
    public function pop(string $queue = null): ?PayloadInterface
    {
        $job = $this->getConnection()
            ->getInstance()
            ->watch($queue?? AdapterInterface::DEFAULT_QUEUE_NAME)
            ->reserve($this->reserveTimeout);

        if ($job instanceof Job) {
            $data = json_decode($job->getData(), true);
            $data[JobInterface::ATTRIBUTE_JOB] = $job;

            return new Payload($data);
        }

        return null;
    }

    /**
     * @inheritdoc
     */
    public function ack(PayloadInterface $payload)
    {
        if (!$this->hasIdAttribute($payload)) {
            throw new InvalidMethodCallException('Job must have an id to be updated on queue.');
        }
        if (!($job = $payload->getAttribute(JobInterface::ATTRIBUTE_JOB)) instanceof Job) {
            throw new InvalidMethodCallException('Payload does not have a valid Beanstalkd job.');
        }

        $queue = $payload->getAttribute(JobInterface::ATTRIBUTE_QUEUE_NAME, AdapterInterface::DEFAULT_QUEUE_NAME);

        /** @var Pheanstalk $store */
        $store = $this->getConnection()->getInstance()->useTube($queue);

        if ($payload->getAttribute(JobInterface::ATTRIBUTE_COMPLETED) === true) {
            $store->delete($job);
        } else {
            // add back to the queue as it wasn't completed maybe due to some transitory error
            // could also be failed.
            $store->release($job, PheanstalkInterface::DEFAULT_PRIORITY, $this->getDelay($payload));
        }
    }

    /**
     * @inheritdoc
     */
    public function isEmpty(string $queue = null): bool
    {
        $stats = $this->getConnection()->getInstance()->statsTube($queue?? AdapterInterface::DEFAULT_QUEUE_NAME);

        return (int)$stats->current_jobs_delayed === 0
            && (int)$stats->current_jobs_urgent === 0
            && (int)$stats->current_jobs_ready === 0;
    }

    /**
     * Returns the seconds to wait before job becomes ready.
     *
     * @param PayloadInterface $payload
     *
     * @return int
     */
    protected function getDelay(PayloadInterface $payload): int
    {
        $delay = (int)$payload->getAttribute(JobInterface::ATTRIBUTE_DELAY, PheanstalkInterface::DEFAULT_DELAY);

        return (int)max(PheanstalkInterface::DEFAULT_DELAY, $delay - time());
    }
}
