<?php
namespace Altair\Courier\Contracts;

interface CommandMessageNameResolverInterface
{
    /**
     * Resolves the name identifier of the message instance.
     *
     * @param CommandMessageInterface $message
     *
     * @return string
     */
    public function resolve(CommandMessageInterface $message): string;
}
