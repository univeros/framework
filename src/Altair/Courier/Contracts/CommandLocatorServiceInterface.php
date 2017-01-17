<?php
namespace Altair\Courier\Contracts;

interface CommandLocatorServiceInterface
{
    /**
     * Checks whether a CommandInterface exists for that particular message name.
     *
     * @param string $name
     *
     * @return bool
     */
    public function has(string $name): bool;

    /**
     * Returns a CommandInterface for that particular message instance.
     *
     * @param string $name
     *
     * @throws \Altair\Courier\Exception\UnknownCommandMessageNameException if not command has been found
     *
     * @return CommandInterface
     */
    public function get(string $name): CommandInterface;
}
