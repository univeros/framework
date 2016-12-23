<?php
namespace Altair\Http\Configuration;

use Altair\Configuration\Contracts\ConfigurationInterface;
use Altair\Container\Container;
use Altair\Container\Definition;
use Altair\Http\Collection\MiddlewareCollection;
use Altair\Http\Resolver\ContainerResolver;
use Relay\Relay;
use Relay\RelayBuilder;
use Relay\ResolverInterface;

class RelayConfiguration implements ConfigurationInterface
{
    public function apply(Container $container)
    {
        $relayBuilderDefinition = new Definition(['resolver' => ResolverInterface::class]);
        $containerResolverDefinition = new Definition([':container' => $container]);
        $factory = function (RelayBuilder $builder, MiddlewareCollection $queue) {
            return $builder->newInstance($queue);
        };

        $container
            ->define(RelayBuilder::class, $relayBuilderDefinition)
            ->define(ContainerResolver::class, $containerResolverDefinition)
            ->delegate(Relay::class, $factory)
            ->alias(
                ResolverInterface::class,
                ContainerResolver::class
            );
    }
}
