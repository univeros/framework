<?php
namespace Altair\Courier\Strategy;

use Altair\Courier\Contracts\CommandLocatorServiceInterface;
use Altair\Courier\Contracts\CommandMessageInterface;
use Altair\Courier\Contracts\CommandMessageNameResolverInterface;
use Altair\Courier\Contracts\CommandRunnerStrategyInterface;

class CommandRunnerExecStrategy implements CommandRunnerStrategyInterface
{
    /**
     * @var CommandLocatorServiceInterface
     */
    private $commandLocator;
    /**
     * @var CommandMessageNameResolverInterface
     */
    private $nameResolver;

    /**
     * ExecuteStrategy constructor.
     *
     * @param CommandLocatorServiceInterface $locator
     * @param CommandMessageNameResolverInterface $resolver
     */
    public function __construct(CommandLocatorServiceInterface $locator, CommandMessageNameResolverInterface $resolver)
    {
        $this->commandLocator = $locator;
        $this->nameResolver = $resolver;
    }

    /**
     * @param CommandMessageInterface $message
     */
    public function run(CommandMessageInterface $message)
    {
        $name = $this->nameResolver->resolve($message);
        $command = $this->commandLocator->get($name);
        $command->exec($message);
    }
}
