<?php
namespace Altair\Courier\Contracts;

interface CommandMiddlewareInterface
{
    /**
     * @param CommandMessageInterface $message
     * @param callable $next
     */
    public function handle(CommandMessageInterface $message, callable $next);
}
