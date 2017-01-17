<?php
namespace Altair\Courier;

use Altair\Courier\Contracts\CommandBusInterface;
use Altair\Courier\Contracts\CommandMessageInterface;
use Altair\Courier\Contracts\CommandRunnerStrategyInterface;

class CommandBus implements CommandBusInterface
{
    /**
     * @var CommandRunnerStrategyInterface
     */
    protected $strategy;

    /**
     * CommandBus constructor.
     *
     * @param CommandRunnerStrategyInterface $strategy
     */
    public function __construct(CommandRunnerStrategyInterface $strategy)
    {
        $this->strategy = $strategy;
    }

    /**
     * @inheritdoc
     */
    public function handle(CommandMessageInterface $message)
    {
        $this->strategy->run($message);
    }
}
