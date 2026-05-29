<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Courier\Configuration;

use Altair\Configuration\Contracts\ConfigurationInterface;
use Altair\Configuration\Traits\EnvAwareTrait;
use Altair\Container\Container;
use Altair\Courier\CommandBus;
use Altair\Courier\Contracts\CommandBusInterface;
use Altair\Courier\Contracts\CommandLocatorServiceInterface;
use Altair\Courier\Contracts\CommandMessageNameResolverInterface;
use Altair\Courier\Contracts\CommandRunnerStrategyInterface;
use Altair\Courier\Contracts\MiddlewareResolverInterface;
use Altair\Courier\Middleware\CommandHandlerMiddleware;
use Altair\Courier\Middleware\CommandLockerMiddleware;
use Altair\Courier\Middleware\CommandLoggerMiddleware;
use Altair\Courier\Resolver\ClassCommandMessageNameResolver;
use Altair\Courier\Resolver\MiddlewareResolver;
use Altair\Courier\Service\InMemoryCommandLocatorService;
use Altair\Courier\Strategy\CommandRunnerMiddlewareStrategy;
use Altair\Courier\Support\MessageCommandMap;
use Altair\Filesystem\Filesystem;
use Override;

class MiddlewareCommandBusConfiguration implements ConfigurationInterface
{
    use EnvAwareTrait;

    /**
     * @inheritDoc
     */
    #[Override]
    public function apply(Container $container): void
    {
        $fs = new Filesystem();

        $container->bind(CommandRunnerMiddlewareStrategy::class)->withParameters([
            'middlewares' => [
                CommandLockerMiddleware::class,
                // Command Logger Middleware
                // ensure a Psr\Log\LoggerInterface is configured
                // on the application's container
                CommandLoggerMiddleware::class,
                CommandHandlerMiddleware::class,
            ],
        ]);
        $container->bind(MiddlewareResolver::class)->withParameters(['container' => $container]);
        $container->bind(MessageCommandMap::class)->withParameters([
            'config' => $fs->getRequiredFileValue($this->env->get('COURIER_MAP_FILE')),
        ]);
        $container->alias(MiddlewareResolverInterface::class, MiddlewareResolver::class);
        $container->alias(CommandMessageNameResolverInterface::class, ClassCommandMessageNameResolver::class);
        $container->alias(CommandLocatorServiceInterface::class, InMemoryCommandLocatorService::class);
        $container->alias(CommandRunnerStrategyInterface::class, CommandRunnerMiddlewareStrategy::class);
        $container->alias(CommandBusInterface::class, CommandBus::class);
    }
}
