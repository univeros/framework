<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Middleware\Configuration;

use Altair\Configuration\Contracts\ConfigurationInterface;
use Altair\Container\Container;
use Altair\Middleware\Contracts\MiddlewareManagerInterface;
use Altair\Middleware\Contracts\MiddlewareResolverInterface;
use Altair\Middleware\Contracts\MiddlewareRunnerInterface;
use Altair\Middleware\MiddlewareManager;
use Altair\Middleware\Resolver\MiddlewareResolver;
use Altair\Middleware\Runner;
use Override;

class MiddlewareConfiguration implements ConfigurationInterface
{
    /**
     * @inheritDoc
     */
    #[Override]
    public function apply(Container $container): void
    {
        $container->bind(MiddlewareResolver::class)->withParameters(['container' => $container]);
        $container->bind(Runner::class)->withParameters(['resolver' => MiddlewareResolverInterface::class]);
        $container->alias(MiddlewareResolverInterface::class, MiddlewareResolver::class);
        $container->alias(MiddlewareRunnerInterface::class, Runner::class);
        $container->alias(MiddlewareManagerInterface::class, MiddlewareManager::class);
    }
}
