<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Queue\Configuration;

use Altair\Configuration\Contracts\ConfigurationInterface;
use Altair\Configuration\Traits\EnvAwareTrait;
use Altair\Container\Container;
use Altair\Container\Definition;
use Altair\Queue\Adapter\PdoAdapter;
use Altair\Queue\Connection\PdoConnection;
use Altair\Queue\Contracts\QueueAdapterInterface;
use Altair\Queue\Contracts\QueueConnectionInterface;

class PdoQueueConfiguration implements ConfigurationInterface
{
    use EnvAwareTrait;

    public function apply(Container $container)
    {
        $connectionDefinition = new Definition(
            [
                ':dsn' => $this->env->get('QUEUE_PDO_DSN'),
                ':username' => $this->env->get('QUEUE_PDO_USERNAME'),
                ':password' => $this->env->get('QUEUE_PDO_PASSWORD')
            ]
        );

        $container
            ->define(PdoConnection::class, $connectionDefinition)
            ->alias(QueueConnectionInterface::class, PdoConnection::class)
            ->alias(QueueAdapterInterface::class, PdoAdapter::class);
    }
}
