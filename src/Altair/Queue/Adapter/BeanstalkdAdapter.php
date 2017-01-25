<?php
namespace Altair\Queue\Adapter;

use Altair\Queue\Connection\BeanstalkdConnection;
use Altair\Queue\Contracts\JobInterface;
use Pheanstalk\Pheanstalk;

/**
 * Class BeanstalkdAdapter
 *
 * @property BeanstalkdConnection $connection
 */
class BeanstalkdAdapter extends AbstractAdapter
{

    protected $queue;
    protected $timeToRun;
    protected $reserveTimeout;


    public function __construct(
        BeanstalkdConnection $connection,
        string $queue = 'altair:queue',
        int $timeToRun = Pheanstalk::DEFAULT_TTR,
        int $reserveTimeout = 5
    ) {
        $this->connection = $connection;
        $this->queue = $queue;
        $this->timeToRun = $timeToRun;
        $this->reserveTimeout = $reserveTimeout;

        $this->connection->connect();
    }


    public function push(JobInterface $job, array $data = [], string $queue = null): bool
    {

    }

    public function pop(string $queue = null): JobInterface
    {
        // TODO: Implement pop() method.
    }

    public function ack(JobInterface $job, string $queue = null)
    {
        // TODO: Implement ack() method.
    }

    public function isEmpty(string $queue = null): bool
    {
        // TODO: Implement isEmpty() method.
    }


}
