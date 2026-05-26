<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Courier;

use Altair\Courier\Contracts\CommandBusInterface;
use Altair\Courier\Contracts\CommandMessageInterface;
use Altair\Courier\Contracts\CommandRunnerStrategyInterface;

class CommandBus implements CommandBusInterface
{
    /**
     * CommandBus constructor.
     */
    public function __construct(protected CommandRunnerStrategyInterface $strategy)
    {
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function handle(CommandMessageInterface $message): void
    {
        $this->strategy->run($message);
    }
}
