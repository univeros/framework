<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Session\Handler;

use Altair\Session\Contracts\PdoSessionAdapterInterface;
use Altair\Session\Contracts\PdoSessionHandlerInterface;
use PDOException;

class PdoSessionHandler implements PdoSessionHandlerInterface
{
    /**
     * @var PdoSessionAdapterInterface
     */
    protected $adapter;
    /**
     * @var bool whether gc() has been called
     */
    protected $gcCalled = false;

    /**
     * PdoSessionHandler constructor.
     *
     * @param PdoSessionAdapterInterface $adapter
     */
    public function __construct(
        PdoSessionAdapterInterface $adapter
    ) {
        $this->adapter = $adapter;
    }

    /**
     * {@inheritDoc}
     */
    public function open($savePath, $sessionName)
    {
        if (!$this->adapter->getIsConnected()) {
            $this->adapter->connect();
        }

        return true;
    }

    public function close()
    {
        $gc = $this->gcCalled;
        $this->gcCalled = false;

        return $this->adapter->close($gc);
    }

    /**
     * {@inheritDoc}
     */
    public function read($sessionId)
    {
        try {
            return $this->adapter->read($sessionId);
        } catch (PDOException $e) {
            $this->adapter->rollback();
            throw $e;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function write($sessionId, $data)
    {
        try {
            return $this->adapter->write($sessionId, $data);
        } catch (PDOException $e) {
            $this->adapter->rollback();
            throw $e;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function destroy($sessionId)
    {
        try {
            return $this->adapter->delete($sessionId);
        } catch (PDOException $e) {
            $this->adapter->rollback();
            throw $e;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function gc($maxlifetime)
    {
        // We delay gc() to close() so that it is executed outside the transactional and blocking read-write process.
        // This way, pruning expired sessions does not block them from being started while the current session is used.
        return $this->gcCalled = true;
    }

    /**
     * Returns whether the session has expired or not.
     *
     * @return bool
     */
    public function getHasSessionExpired(): bool
    {
        return $this->adapter->getHasSessionExpired();
    }
}
