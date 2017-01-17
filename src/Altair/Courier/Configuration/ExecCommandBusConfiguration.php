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
use Altair\Courier\Resolver\ClassCommandMessageNameResolver;
use Altair\Courier\Service\InMemoryCommandLocatorService;
use Altair\Courier\Strategy\CommandRunnerExecStrategy;
use Altair\Courier\Support\MessageCommandMap;
use Altair\Filesystem\Filesystem;

class ExecCommandBusConfiguration implements ConfigurationInterface
{
    use EnvAwareTrait;

    /**
     * @inheritdoc
     */
    public function apply(Container $container)
    {
        $fs = new Filesystem();

        $factory = function () use ($fs) {
            // The file should contain all the mapping definitions
            // If using ClassCommandMessageNameResolver and InMemoryCommandLocatorService:
            // [ YourCommandMessage::class => YourCommandHandler::class]
            //
            // If using CommandMessageNameResolver and CallableCommandLocatorService:
            // [ YourCommandMessage::class => function() { return new YourCommandHandler(); } ]

            $config = $fs->getRequiredFileValue($this->env->get('COU_MAP_FILE'));

            $map = new MessageCommandMap($config);

            return new InMemoryCommandLocatorService($map);
        };

        $container
            ->delegate(InMemoryCommandLocatorService::class, $factory)
            ->alias(CommandMessageNameResolverInterface::class, ClassCommandMessageNameResolver::class)
            ->alias(CommandLocatorServiceInterface::class, InMemoryCommandLocatorService::class)
            ->alias(CommandRunnerStrategyInterface::class, CommandRunnerExecStrategy::class)
            ->alias(CommandBusInterface::class, CommandBus::class);
    }
}
