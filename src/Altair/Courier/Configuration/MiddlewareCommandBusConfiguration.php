<?php
namespace Altair\Courier\Configuration;

use Altair\Configuration\Contracts\ConfigurationInterface;
use Altair\Configuration\Traits\EnvAwareTrait;
use Altair\Container\Container;
use Altair\Courier\CommandBus;
use Altair\Courier\Contracts\CommandBusInterface;
use Altair\Courier\Contracts\CommandLocatorServiceInterface;
use Altair\Courier\Contracts\CommandMessageNameResolverInterface;
use Altair\Courier\Contracts\CommandRunnerStrategyInterface;
use Altair\Courier\Middleware\CommandHandlerMiddleware;
use Altair\Courier\Middleware\CommandLockerMiddleware;
use Altair\Courier\Middleware\CommandLoggerMiddleware;
use Altair\Courier\Resolver\ClassCommandMessageNameResolver;
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

        $locatorFactory = function () use ($fs) {
            // The file should contain all the mapping definitions
            // If using ClassCommandMessageNameResolver:
            // [ YourCommandMessage::class => YourCommandHandler::class]

            $config = $fs->getRequiredFileValue($this->env->get('COU_MAP_FILE'));

            $map = new MessageCommandMap($config);

            return new InMemoryCommandLocatorService($map);
        };

        $runnerFactory = function () use ($container) {
            $middlewares = [
                $container->make(CommandLockerMiddleware::class),
                $container->make(CommandLoggerMiddleware::class),
                $container->make(CommandHandlerMiddleware::class)
            ];

            return new CommandRunnerMiddlewareStrategy($middlewares);
        };

        $container
            ->delegate(CommandRunnerMiddlewareStrategy::class, $runnerFactory)
            ->delegate(InMemoryCommandLocatorService::class, $locatorFactory)
            ->alias(CommandMessageNameResolverInterface::class, ClassCommandMessageNameResolver::class)
            ->alias(CommandLocatorServiceInterface::class, InMemoryCommandLocatorService::class)
            ->alias(CommandRunnerStrategyInterface::class, CommandRunnerMiddlewareStrategy::class)
            ->alias(CommandBusInterface::class, CommandBus::class);
    }
}
