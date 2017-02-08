<?php
namespace Altair\Queue\Connection;

use Altair\Queue\Contracts\ConnectionInterface;
use Altair\Queue\Traits\ConnectionInstanceAwareTrait;
use Pheanstalk\Pheanstalk;
use Pheanstalk\PheanstalkInterface;

class BeanstalkdConnection implements ConnectionInterface
{
    use ConnectionInstanceAwareTrait;

    protected $host;
    protected $port;
    protected $connectionTimeout;
    protected $connectPersistent;

    /**
     * BeanstalkdConnection constructor.
     *
     * @param string $host
     * @param int $port
     * @param int|null $connectionTimeout
     * @param bool $connectPersistent
     */
    public function __construct(
        string $host,
        int $port = PheanstalkInterface::DEFAULT_PORT,
        int $connectionTimeout = null,
        bool $connectPersistent = false
    ) {
        $this->host = $host;
        $this->port = $port;
        $this->connectionTimeout = $connectionTimeout;
        $this->connectPersistent = $connectPersistent;
    }

    /**
     * @inheritdoc
     */
    public function connect(): ConnectionInterface
    {
        $this->disconnect();
        $this->instance = new Pheanstalk($this->host, $this->port, $this->connectionTimeout, $this->connectPersistent);

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function disconnect(): bool
    {
        if ($this->instance instanceof Pheanstalk && $this->instance->getConnection()->hasSocket()) {
            $this->instance->getConnection()->disconnect();
        }
        $this->instance = null;

        return true;
    }
}
