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
use Altair\Http\Collection\MiddlewareCollection;
use Altair\Http\Resolver\ContainerResolver;
use Override;
use Relay\Relay;

class RelayConfiguration implements ConfigurationInterface
{
    #[Override]
    public function apply(Container $container): void
    {
        $container->bind(ContainerResolver::class)->withParameters(['container' => $container]);
        $container->factory(
            Relay::class,
            static fn(
                MiddlewareCollection $queue,
                ContainerResolver $resolver,
            ): Relay => new Relay($queue->toArray(), $resolver),
        );
    }
}
