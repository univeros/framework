<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

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
    /**
     * @inheritdoc
     */
    public function apply(Container $container): void
    {
        $relayBuilderDefinition = new Definition(['resolver' => ResolverInterface::class]);
        $containerResolverDefinition = new Definition([':container' => $container]);
        $factory = function (RelayBuilder $builder, MiddlewareCollection $queue): Relay {
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
