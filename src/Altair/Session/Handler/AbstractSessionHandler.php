<?php
namespace Altair\Session\Handler;

use SessionHandlerInterface;

abstract class AbstractSessionHandler implements SessionHandlerInterface
{
    public function getIsActive()
    {
        return session_status() === PHP_SESSION_ACTIVE;
    }
}
