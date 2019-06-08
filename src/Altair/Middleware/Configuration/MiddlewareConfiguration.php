<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Middleware\Configuration;

use Altair\Configuration\Contracts\ConfigurationInterface;
use Altair\Container\Container;
use Altair\Container\Definition;
use Altair\Middleware\Contracts\MiddlewareManagerInterface;
use Altair\Middleware\Contracts\MiddlewareResolverInterface;
use Altair\Middleware\Contracts\MiddlewareRunnerInterface;
use Altair\Middleware\MiddlewareManager;
use Altair\Middleware\Resolver\MiddlewareResolver;
use Altair\Middleware\Runner;

class MiddlewareConfiguration implements ConfigurationInterface
{
    /**
     * @inheritdoc
     */
    public function apply(Container $container): void
    {
        $container
            ->define(MiddlewareResolver::class, (new Definition([':container' => $container])))
            ->define(Runner::class, (new Definition([':resolver' => MiddlewareResolverInterface::class])))
            ->alias(MiddlewareResolverInterface::class, MiddlewareResolver::class)
            ->alias(MiddlewareRunnerInterface::class, Runner::class)
            ->alias(MiddlewareManagerInterface::class, MiddlewareManager::class);
    }
}
