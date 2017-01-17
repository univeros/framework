<?php
namespace Altair\Courier\Contracts;

interface CommandRunnerStrategyInterface
{
    /**
     * Runs a strategy to run for command message processing.
     *
     * @param CommandMessageInterface $message
     *
     * @return void
     */
    public function run(CommandMessageInterface $message);
}
