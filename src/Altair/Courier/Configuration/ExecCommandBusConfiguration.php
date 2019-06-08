<?php declare(strict_types=1);

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
    public function apply(Container $container): void
    {
        $fs = new Filesystem();

        $factory = function () use ($fs) {
            // The file should contain all the mapping definitions
            // If using ClassCommandMessageNameResolver and InMemoryCommandLocatorService:
            // [ YourCommandMessage::class => YourCommandHandler::class]
            //
            // If using CommandMessageNameResolver and CallableCommandLocatorService:
            // [ 'name-in-message-class' => function() { return new YourCommand(); } ]

            $config = $fs->getRequiredFileValue($this->env->get('COURIER_MAP_FILE'));

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
