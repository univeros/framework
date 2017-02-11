<?php
namespace Altair\Queue\Connection;

use Altair\Queue\Contracts\ConnectionInterface;
use Altair\Queue\Traits\ConnectionInstanceAwareTrait;
use Predis\Client;

class RedisConnection implements ConnectionInterface
{
    use ConnectionInstanceAwareTrait;

    /**
     * @var array
     */
    protected $params;

    /**
     * RedisConnection constructor.
     *
     * @see https://github.com/nrk/predis/wiki/Connection-Parameters#list-of-connection-parameters for a full list
     * of connection parameters
     *
     * @param array $params
     */
    public function __construct(array $params)
    {
        $this->params = $params;
        $this->connect();
    }

    /**
     * @inheritdoc
     */
    public function connect(): ConnectionInterface
    {
        $this->disconnect();
        $this->instance = new Client($this->params);

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function disconnect(): bool
    {
        if ($this->instance instanceof Client && $this->instance->isConnected()) {
            $this->instance->disconnect();
        }

        $this->instance = null;

        return true;
    }
}
