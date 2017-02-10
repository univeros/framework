<?php
namespace Altair\Queue\Configuration;

use Altair\Configuration\Contracts\ConfigurationInterface;
use Altair\Configuration\Traits\EnvAwareTrait;
use Altair\Container\Container;
use Altair\Container\Definition;
use Altair\Queue\Adapter\SqsAdapter;
use Altair\Queue\Connection\SqsConnection;
use Altair\Queue\Contracts\AdapterInterface;
use Altair\Queue\Contracts\ConnectionInterface;

class SqsQueueConfiguration implements ConfigurationInterface
{
    use EnvAwareTrait;

    public function apply(Container $container)
    {
        $connectionDefinition = new Definition(
            [
                ':key' => $this->env->get('QUEUE_SQS_KEY'),
                ':secret' => $this->env->get('QUEUE_SQS_SECRET'),
                ':region' => $this->env->get('QUEUE_SQS_REGION')
            ]
        );

        $container
            ->define(SqsConnection::class, $connectionDefinition)
            ->alias(ConnectionInterface::class, SqsConnection::class)
            ->alias(AdapterInterface::class, SqsAdapter::class);
    }
}
