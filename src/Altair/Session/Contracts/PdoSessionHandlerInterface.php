<?php
namespace Altair\Session\Contracts;

interface PdoSessionHandlerInterface extends \SessionHandlerInterface
{
    /**
     * Returns whether the session has expired or not.
     *
     * @return bool
     */
    public function getHasSessionExpired(): bool;
}
