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
use Altair\Queue\Adapter\BeanstalkdAdapter;
use Altair\Queue\Connection\BeanstalkdConnection;
use Altair\Queue\Contracts\QueueAdapterInterface;
use Altair\Queue\Contracts\QueueConnectionInterface;
use Pheanstalk\PheanstalkInterface;

class BeanstalkdQueueConfiguration implements ConfigurationInterface
{
    use EnvAwareTrait;

    public function apply(Container $container)
    {
        $connectionDefinition = new Definition(
            [
                ':host' => $this->env->get('QUEUE_BEANSTALKD_HOST'),
                ':port' => $this->env->get('QUEUE_BEANSTALKD_PORT', PheanstalkInterface::DEFAULT_PORT),
                ':connectionTimeout' => $this->env->get('QUEUE_BEANSTALKD_CONNECTION_TIMEOUT'),
                ':connectPersistent' => $this->env->get('QUEUE_BEANSTALKD_CONNECT_PERSISTENT', false)
            ]
        );

        $adapterDefinition = new Definition(
            [
                ':timeToRun' => $this->env->get('QUEUE_BEANSTALKD_TTR', PheanstalkInterface::DEFAULT_TTR),
                ':reserveTimeout' => $this->env->get('QUEUE_BEANSTALKD_RESERVE_TIMEOUT', 5)
            ]
        );

        $container
            ->define(BeanstalkdConnection::class, $connectionDefinition)
            ->define(BeanstalkdAdapter::class, $adapterDefinition)
            ->alias(QueueConnectionInterface::class, BeanstalkdConnection::class)
            ->alias(QueueAdapterInterface::class, BeanstalkdAdapter::class);
    }
}
