<?php

declare(strict_types=1);

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
use Altair\Courier\Exception\UnknownCommandMessageNameException;
use Override;

class CommandRunnerExecStrategy implements CommandRunnerStrategyInterface
{
    /**
     * ExecuteStrategy constructor.
     */
    public function __construct(private readonly CommandLocatorServiceInterface $commandLocator, private readonly CommandMessageNameResolverInterface $nameResolver) {}

    /**
     * @throws UnknownCommandMessageNameException
     */
    #[Override]
    public function run(CommandMessageInterface $message): void
    {
        $name = $this->nameResolver->resolve($message);
        $command = $this->commandLocator->get($name);
        $command->exec($message);
    }
}
