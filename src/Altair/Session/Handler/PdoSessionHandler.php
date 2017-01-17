<?php
namespace Altair\Session\Handler;

use SessionHandlerInterface;

class PdoSessionHandler implements SessionHandlerInterface
{
    public function open($savePath, $sessionName)
    {
        // TODO: Implement open() method.
    }

    public function close()
    {
        // TODO: Implement close() method.
    }

    public function read($sessionId)
    {
        // TODO: Implement read() method.
    }

    public function write($sessionId, $data)
    {
        // TODO: Implement write() method.
    }

    public function destroy($sessionId)
    {
        // TODO: Implement destroy() method.
    }

    public function gc($maxlifetime)
    {
        // TODO: Implement gc() method.
    }
}
