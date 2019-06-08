<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

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
     * @throws \Altair\Courier\Exception\UnknownCommandMessageNameException
     */
    public function run(CommandMessageInterface $message): void
    {
        $name = $this->nameResolver->resolve($message);
        $command = $this->commandLocator->get($name);
        $command->exec($message);
    }
}
