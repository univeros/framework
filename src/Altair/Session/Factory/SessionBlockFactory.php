<?php
namespace Altair\Session\Factory;

use Altair\Session\Contracts\SessionManagerInterface;
use Altair\Session\SessionBlock;

class SessionBlockFactory
{
    /**
     * Creates a new session block.
     *
     * @param string $name
     * @param SessionManagerInterface $sessionManager
     *
     * @return SessionBlock
     */
    public static function create(string $name, SessionManagerInterface $sessionManager): SessionBlock
    {
        return new SessionBlock($name, $sessionManager);
    }
}
