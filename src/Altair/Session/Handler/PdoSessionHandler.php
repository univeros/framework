<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Session\Handler;

use Altair\Session\Contracts\PdoSessionAdapterInterface;
use Altair\Session\Contracts\PdoSessionHandlerInterface;
use Override;
use PDOException;
use ReturnTypeWillChange;

class PdoSessionHandler implements PdoSessionHandlerInterface
{
    /**
     * @var bool whether gc() has been called
     */
    protected $gcCalled = false;

    /**
     * PdoSessionHandler constructor.
     */
    public function __construct(protected PdoSessionAdapterInterface $adapter) {}

    /**
     * {@inheritDoc}
     */
    #[ReturnTypeWillChange]
    #[Override]
    public function open($savePath, $sessionName)
    {
        if (!$this->adapter->getIsConnected()) {
            $this->adapter->connect();
        }

        return true;
    }

    #[ReturnTypeWillChange]
    #[Override]
    public function close()
    {
        $gc = $this->gcCalled;
        $this->gcCalled = false;

        return $this->adapter->close($gc);
    }

    /**
     * {@inheritDoc}
     */
    #[ReturnTypeWillChange]
    #[Override]
    public function read($sessionId)
    {
        try {
            return $this->adapter->read($sessionId);
        } catch (PDOException $pdoException) {
            $this->adapter->rollback();
            throw $pdoException;
        }
    }

    /**
     * {@inheritDoc}
     */
    #[ReturnTypeWillChange]
    #[Override]
    public function write($sessionId, $data)
    {
        try {
            return $this->adapter->write($sessionId, $data);
        } catch (PDOException $pdoException) {
            $this->adapter->rollback();
            throw $pdoException;
        }
    }

    /**
     * {@inheritDoc}
     */
    #[ReturnTypeWillChange]
    #[Override]
    public function destroy($sessionId)
    {
        try {
            return $this->adapter->delete($sessionId);
        } catch (PDOException $pdoException) {
            $this->adapter->rollback();
            throw $pdoException;
        }
    }

    /**
     * {@inheritDoc}
     */
    #[ReturnTypeWillChange]
    #[Override]
    public function gc($maxlifetime): int|false
    {
        // We delay gc() to close() so that it is executed outside the transactional and blocking read-write process.
        // This way, pruning expired sessions does not block them from being started while the current session is used.
        // The actual pruning (and its count) happens in close(); at this point nothing has been deleted yet.
        $this->gcCalled = true;

        return 0;
    }

    /**
     * Returns whether the session has expired or not.
     */
    #[Override]
    public function getHasSessionExpired(): bool
    {
        return $this->adapter->getHasSessionExpired();
    }
}
