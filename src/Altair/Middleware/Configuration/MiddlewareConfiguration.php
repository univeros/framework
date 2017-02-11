<?php
namespace Altair\Middleware\Configuration;

use Altair\Configuration\Contracts\ConfigurationInterface;
use Altair\Container\Container;
use Altair\Container\Definition;
use Altair\Middleware\Contracts\MiddlewareResolverInterface;
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
            ->define(Runner::class, (new Definition([':resolver' => MiddlewareResolverInterface::class])))
            ->define(MiddlewareResolver::class, (new Definition([':container' => $container])))
            ->alias(MiddlewareResolverInterface::class, MiddlewareResolver::class);
    }

}
