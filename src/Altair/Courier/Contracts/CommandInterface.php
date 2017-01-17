<?php
namespace Altair\Courier\Contracts;

interface CommandInterface
{
    /**
     * Process a message
     *
     * @param CommandMessageInterface $message
     *
     * @return void
     */
    public function exec(CommandMessageInterface $message);
}
