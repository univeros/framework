<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Queue\Connection;

use Altair\Queue\Contracts\QueueConnectionInterface;
use Altair\Queue\Traits\ConnectionInstanceAwareTrait;
use Predis\Client;

class PredisConnection implements QueueConnectionInterface
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
     * @inheritDoc
     */
    public function connect(): QueueConnectionInterface
    {
        $this->disconnect();
        $this->instance = new Client($this->params);

        return $this;
    }

    /**
     * @inheritDoc
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
