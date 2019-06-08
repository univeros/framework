<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Session\Traits;

use Altair\Session\Contracts\PdoSessionAdapterInterface;
use PDO;
use PDOException;
use PDOStatement;

trait PdoSessionAdapterAwareTrait
{
    /**
     * @var PDO|null
     */
    protected $pdo;
    /**
     * @var array DB connection options
     */
    protected $options;
    /**
     * @var string DNS string
     */
    protected $dsn;
    /**
     * @var string the DB username
     */
    protected $username;
    /**
     * @var string the DB password
     */
    protected $password;
    /**
     * @var string the DB table name to store sessions
     */
    protected $table;
    /**
     * @var int DB lock mode
     */
    protected $lockMode;
    /**
     * @var bool true when the current session exists but expired according to session.gc_maxlifetime
     */
    protected $expired = false;
    /**
     * @var bool whether a transaction is active
     */
    protected $activeTransaction = false;
    /**
     * It's an array to support multiple reads before closing which is manual, non-standard usage.
     *
     * @var PDOStatement[] An array of statements to release advisory locks
     */
    protected $unlockStatements = [];
    /**
     * @var bool True when the current session exists but expired according to session.gc_maxlifetime
     */
    protected $sessionExpired = false;

    /**
     * PdoSessionHandler constructor.
     *
     * @param string $dsn
     * @param string $username
     * @param string $password
     * @param string $table
     * @param int|null $lockMode
     * @param array $options
     */
    public function __construct(
        string $dsn,
        string $username,
        string $password,
        string $table,
        int $lockMode = null,
        array $options = []
    ) {
        $this->dsn = $dsn;
        $this->username = $username;
        $this->password = $password;
        $this->table = $table;
        $this->lockMode = $lockMode?? PdoSessionAdapterInterface::LOCK_TRANSACTIONAL;
        $this->options = $options;
    }

    /**
     * @inheritDoc
     */
    public function getIsConnected(): bool
    {
        return null !== $this->pdo && $this->pdo instanceof PDO;
    }

    /**
     * @return bool
     */
    public function getHasSessionExpired(): bool
    {
        return $this->sessionExpired;
    }

    /**
     * @inheritDoc
     */
    public function getConnection(): PDO
    {
        if (null === $this->pdo) {
            $this->connect();
        }

        return $this->pdo;
    }

    /**
     * @inheritDoc
     */
    public function connect()
    {
        $this->pdo = new PDO($this->dsn, $this->username, $this->password, $this->options);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    /**
     * @inheritDoc
     */
    public function read(string $sessionId): string
    {
        $this->sessionExpired = false;

        if (PdoSessionAdapterInterface::LOCK_ADVISORY === $this->lockMode) {
            $this->unlockStatements[] = $this->doAdvisoryLocking($sessionId);
        }

        if ($this->getIsLockModeTransactional()) {
            $this->beginTransaction();
        }

        $sql = $this->getSelectSql();
        $query = $this->getConnection()->prepare($sql);
        $query->bindParam(':id', $sessionId, PDO::PARAM_STR);

        do {
            $query->execute();
            $rows = $query->fetchAll(PDO::FETCH_NUM);

            if ($rows) {
                if ($this->checkIfSessionExpired($rows[0][1], $rows[0][2])) {
                    return '';
                }

                return is_resource($rows[0][0]) ? stream_get_contents($rows[0][0]) : $rows[0][0];
            }
            if ($this->getIsLockModeTransactional() &&
                PdoSessionAdapterInterface::DRIVER_SQLITE !== $this->getDriver()
            ) {
                try {
                    $query = $this
                        ->getConnection()
                        ->prepare(
                            sprintf(
                                'INSERT INTO %s (id, content, session_lifetime, session_time) ' .
                                'VALUES (:id, :content, :lifetime, :session_time)',
                                $this->table
                            )
                        );

                    $query->bindParam(':id', $sessionId, PDO::PARAM_STR);
                    $query->bindValue(':content', '', PDO::PARAM_STR);
                    $query->bindValue(':lifetime', 0, PDO::PARAM_INT);
                    $query->bindValue(':session_time', time(), PDO::PARAM_INT);
                    $query->execute();
                } catch (PDOException $e) {
                    // Catch duplicate key error because other connection created the session already.
                    // It would only not be the case when the other connection destroyed the session.
                    if (0 === strpos($e->getCode(), '23')) {
                        // Retrieve finished session data written by concurrent connection by restarting the loop.
                        // We have to start a new transaction as a failed query will mark the current transaction as
                        // aborted in PostgreSQL and disallow further queries within it.
                        $this->rollback();
                        $this->beginTransaction();
                        continue;
                    }
                    throw $e;
                }
            }

            return '';
        } while (true);
    }

    /**
     * @inheritDoc
     */
    public function write(string $sessionId, string $data): bool
    {
        $maxlifetime = (int)ini_get('session.gc_maxlifetime');

        $mergeQuery = $this->getMergePdoStatement($sessionId, $data);
        if (null !== $mergeQuery && $mergeQuery instanceof PDOStatement) {
            $mergeQuery->execute();

            return true;
        }

        $updateQuery = $this
            ->getConnection()
            ->prepare(
                sprintf(
                    'UPDATE %s SET content=:content, session_lifetime=:lifetime, session_time=:session_time ' .
                    'WHERE id=:id',
                    $this->table
                )
            );
        $updateQuery->bindParam(':id', $sessionId, PDO::PARAM_STR);
        $updateQuery->bindParam(':content', $data, PDO::PARAM_STR);
        $updateQuery->bindParam(':lifetime', $maxlifetime, PDO::PARAM_INT);
        $updateQuery->bindValue(':session_time', time(), PDO::PARAM_INT);
        $updateQuery->execute();

        // When MERGE is not supported, like in Postgres < 9.5, we have to use this approach that can result in
        // duplicate key errors when the same session is written simultaneously (given the LOCK_NONE behavior).
        // We can just catch such an error and re-execute the update. This is similar to a serializable
        // transaction with retry logic on serialization failures but without the overhead and without possible
        // false positives due to longer gap locking.
        // - thanks @symfony
        if (!$updateQuery->rowCount()) {
            try {
                $insertQuery = $this
                    ->getConnection()
                    ->prepare(
                        sprintf(
                            'INSERT INTO %s (id, content, session_lifetime, session_time) ' .
                            'VALUES (:id, :content, :lifetime, :session_time)',
                            $this->table
                        )
                    );
                $insertQuery->bindParam(':id', $sessionId, PDO::PARAM_STR);
                $insertQuery->bindParam(':content', $data, PDO::PARAM_LOB);
                $insertQuery->bindParam(':lifetime', $maxlifetime, PDO::PARAM_INT);
                $insertQuery->bindValue(':session_time', time(), PDO::PARAM_INT);
                $insertQuery->execute();
            } catch (PDOException $e) {
                // Handle integrity violation SQLSTATE 23000 (or a subclass like 23505 in Postgres) for duplicate keys
                if (0 === strpos($e->getCode(), '23')) {
                    $updateQuery->execute();
                } else {
                    throw $e;
                }
            }
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function beginTransaction()
    {
        if (!$this->activeTransaction) {
            if (PdoSessionAdapterInterface::DRIVER_SQLITE === $this->getDriver()) {
                $this->getConnection()->exec('BEGIN IMMEDIATE TRANSACTION');
            } else {
                if (PdoSessionAdapterInterface::DRIVER_MYSQL === $this->getDriver()) {
                    $this->getConnection()->exec('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
                }
                $this->getConnection()->beginTransaction();
            }
            $this->activeTransaction = true;
        }
    }

    /**
     * @inheritDoc
     */
    public function rollback()
    {
        // We only need to rollback if we are in a transaction. Otherwise the resulting
        // error would hide the real problem why rollback was called. We might not be
        // in a transaction when not using the transactional locking behavior or when
        // two callbacks (e.g. destroy and write) are invoked that both fail.
        if ($this->activeTransaction) {
            if (PdoSessionAdapterInterface::DRIVER_SQLITE === $this->getDriver()) {
                $this->getConnection()->exec('ROLLBACK');
            } else {
                $this->getConnection()->rollBack();
            }
            $this->activeTransaction = false;
        }
    }

    /**
     * @inheritDoc
     */
    public function commit()
    {
        if ($this->activeTransaction) {
            try {
                // commit read-write transaction which also releases the lock
                if (PdoSessionAdapterInterface::DRIVER_SQLITE === $this->getDriver()) {
                    $this->getConnection()->exec('COMMIT');
                } else {
                    $this->getConnection()->commit();
                }
                $this->activeTransaction = false;
            } catch (PDOException $e) {
                $this->rollback();
                throw $e;
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function delete(string $sessionId): bool
    {
        // delete the record associated with this id
        $sql = sprintf('DELETE FROM %s WHERE id = :id', $this->table);

        $query = $this->getConnection()->prepare($sql);
        $query->bindParam(':id', $sessionId, PDO::PARAM_STR);
        $query->execute();

        return true;
    }

    /**
     * @inheritDoc
     */
    public function close(bool $gcCalled = false): bool
    {
        $this->commit();

        $this->executeUnlockStatements();

        if ($gcCalled) {
            $sql = sprintf('DELETE FROM %s WHERE (session_lifetime + session_time) < :session_time', $this->table);
            $query = $this->getConnection()->prepare($sql);
            $query->bindValue(':session_time', time(), PDO::PARAM_INT);
            $query->execute();
        }

        $this->pdo = null;

        return true;
    }

    /**
     * Checks whether a transaction has expired.
     *
     * @param int $lifetime
     * @param int $createdAt
     *
     * @return bool
     */
    protected function checkIfSessionExpired(int $lifetime, int $createdAt): bool
    {
        return $this->sessionExpired = ($lifetime + $createdAt < time());
    }

    /**
     * Executed unlock statements if any
     */
    protected function executeUnlockStatements()
    {
        while ($query = array_shift($this->unlockStatements)) {
            $query->execute();
        }
    }

    /**
     * Returns whether the lock mode is transactional
     *
     * @return bool
     */
    protected function getIsLockModeTransactional(): bool
    {
        return PdoSessionAdapterInterface::LOCK_TRANSACTIONAL === $this->lockMode;
    }
}
