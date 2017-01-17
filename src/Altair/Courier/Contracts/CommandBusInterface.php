<?php
namespace Altair\Courier\Contracts;

interface CommandBusInterface
{
    /**
     * Executes a command message by applying the strategy that it was instantiated withs.
     *
     * @param CommandMessageInterface $message
     *
     * @return mixed
     */
    public function handle(CommandMessageInterface $message);
}
