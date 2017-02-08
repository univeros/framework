<?php
namespace Altair\Queue\Configuration;

use Altair\Configuration\Contracts\ConfigurationInterface;
use Altair\Configuration\Traits\EnvAwareTrait;
use Altair\Container\Container;
use Altair\Container\Definition;
use Altair\Queue\Adapter\RedisAdapter;
use Altair\Queue\Connection\RedisConnection;
use Altair\Queue\Contracts\AdapterInterface;
use Altair\Queue\Contracts\ConnectionInterface;

class RedisQueueConfiguration implements ConfigurationInterface
{
    use EnvAwareTrait;

    public function apply(Container $container)
    {
        $connectionDefinition = new Definition(
            [
                ':params' => [
                    'host' => $this->env->get('QUEUE_REDIS_HOST', 'localhost'),
                    'port' => $this->env->get('QUEUE_REDIS_PORT', 9367)
                ]
            ]
        );

        $adapterDefinition = new Definition(
            [
                ':expireTime' => $this->env->get('QUEUE_REDIS_EXPIRE_TIME', 60)
            ]
        );

        $container
            ->define(RedisConnection::class, $connectionDefinition)
            ->define(RedisAdapter::class, $adapterDefinition)
            ->alias(ConnectionInterface::class, RedisConnection::class)
            ->alias(AdapterInterface::class, RedisAdapter::class);
    }
}
