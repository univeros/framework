<?php
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
    public function apply(Container $container)
    {
        $container
            ->define(MiddlewareResolver::class, (new Definition([':container' => $container])))
            ->define(Runner::class, (new Definition([':resolver' => MiddlewareResolverInterface::class])))
            ->alias(MiddlewareResolverInterface::class, MiddlewareResolver::class)
            ->alias(MiddlewareRunnerInterface::class, Runner::class)
            ->alias(MiddlewareManagerInterface::class, MiddlewareManager::class);
    }
}
