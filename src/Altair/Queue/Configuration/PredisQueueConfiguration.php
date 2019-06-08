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
use Altair\Queue\Adapter\PredisAdapter;
use Altair\Queue\Connection\PredisConnection;
use Altair\Queue\Contracts\QueueAdapterInterface;
use Altair\Queue\Contracts\QueueConnectionInterface;

class PredisQueueConfiguration implements ConfigurationInterface
{
    use EnvAwareTrait;

    public function apply(Container $container): void
    {
        $connectionDefinition = new Definition(
            [
                ':params' => [
                    'host' => $this->env->get('QUEUE_REDIS_HOST', 'localhost'),
                    'port' => $this->env->get('QUEUE_REDIS_PORT', 6379)
                ]
            ]
        );

        $adapterDefinition = new Definition(
            [
                ':expireTime' => $this->env->get('QUEUE_REDIS_EXPIRE_TIME', 60)
            ]
        );

        $container
            ->define(PredisConnection::class, $connectionDefinition)
            ->define(PredisAdapter::class, $adapterDefinition)
            ->alias(QueueConnectionInterface::class, PredisConnection::class)
            ->alias(QueueAdapterInterface::class, PredisAdapter::class);
    }
}
