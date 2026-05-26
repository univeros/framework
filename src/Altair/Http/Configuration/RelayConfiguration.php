<?php

declare(strict_types=1);

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

class RelayConfiguration implements ConfigurationInterface
{
    #[\Override]
    public function apply(Container $container): void
    {
        $container
            ->define(ContainerResolver::class, new Definition([':container' => $container]))
            ->delegate(
                Relay::class,
                static fn (
                    MiddlewareCollection $queue,
                    ContainerResolver $resolver,
                ): Relay => new Relay($queue->toArray(), $resolver),
            );
    }
}
