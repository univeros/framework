<?php
namespace Altair\Courier\Configuration;

use Altair\Configuration\Contracts\ConfigurationInterface;
use Altair\Configuration\Traits\EnvAwareTrait;
use Altair\Container\Container;
use Altair\Container\Definition;
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

class MiddlewareCommandBusConfiguration implements ConfigurationInterface
{
    use EnvAwareTrait;

    /**
     * @inheritdoc
     */
    public function apply(Container $container)
    {
        $fs = new Filesystem();

        $mapDefinition = new Definition([':config' => $fs->getRequiredFileValue($this->env->get('COU_MAP_FILE'))]);
        $resolverDefinition = new Definition([':container' => $container]);
        $runnerDefinition = new Definition(
            [
                ':middlewares' => [
                    CommandLockerMiddleware::class,
                    // Command Logger Middleware
                    // ensure a Psr\Log\LoggerInterface is configured
                    // on the application's container
                    CommandLoggerMiddleware::class,
                    CommandHandlerMiddleware::class
                ]
            ]
        );

        $container
            ->define(CommandRunnerMiddlewareStrategy::class, $runnerDefinition)
            ->define(MiddlewareResolver::class, $resolverDefinition)
            ->define(MessageCommandMap::class, $mapDefinition)
            ->alias(MiddlewareResolverInterface::class, MiddlewareResolver::class)
            ->alias(CommandMessageNameResolverInterface::class, ClassCommandMessageNameResolver::class)
            ->alias(CommandLocatorServiceInterface::class, InMemoryCommandLocatorService::class)
            ->alias(CommandRunnerStrategyInterface::class, CommandRunnerMiddlewareStrategy::class)
            ->alias(CommandBusInterface::class, CommandBus::class);
    }
}
