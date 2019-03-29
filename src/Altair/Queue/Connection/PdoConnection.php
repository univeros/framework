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
use PDO;

class PdoConnection implements QueueConnectionInterface
{
    use ConnectionInstanceAwareTrait;

    protected $dsn;
    protected $username;
    protected $password;
    protected $options;

    /**
     * PdoConnection constructor.
     *
     * @param string $dsn
     * @param string $username
     * @param string $password
     * @param array $options
     */
    public function __construct(string $dsn, string $username, string $password, array $options = [])
    {
        $this->dsn = $dsn;
        $this->username = $username;
        $this->password = $password;
        $this->options = $options;
        $this->connect();
    }

    /**
     * @inheritdoc
     */
    public function connect(): QueueConnectionInterface
    {
        $this->disconnect();
        $this->instance = new PDO($this->dsn, $this->username, $this->password, $this->options);
        $this->instance->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function disconnect(): bool
    {
        if (null !== $this->instance) {
            $this->instance = null;
        }

        return $this->instance === null;
    }
}
