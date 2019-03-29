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
use Altair\Queue\Adapter\SqsAdapter;
use Altair\Queue\Connection\SqsConnection;
use Altair\Queue\Contracts\QueueAdapterInterface;
use Altair\Queue\Contracts\QueueConnectionInterface;

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
            ->alias(QueueConnectionInterface::class, SqsConnection::class)
            ->alias(QueueAdapterInterface::class, SqsAdapter::class);
    }
}
