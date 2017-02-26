<?php
namespace Altair\Session\Contracts;

use PDO;
use PDOStatement;

interface PdoSessionAdapterInterface
{
    /**
     * Driver name mysql
     */
    const DRIVER_MYSQL = 'mysql';
    /**
     * Driver name sqlite
     */
    const DRIVER_SQLITE = 'sqlite';
    /**
     * Driver name postgresql
     */
    const DRIVER_POSTGRESQL = 'postgresql';
    /**
     * No locking is done. This means sessions are prone to loss of data due to
     * race conditions of concurrent requests to the same session. The last session
     * write will win in this case. It might be useful when you implement your own
     * logic to deal with this like an optimistic approach.
     */
    const LOCK_NONE = 0;
    /**
     * Creates an application-level lock on a session. The disadvantage is that the
     * lock is not enforced by the database and thus other, unaware parts of the
     * application could still concurrently modify the session. The advantage is it
     * does not require a transaction.
     * This mode is not available for SQLite and not yet implemented for oci and sqlsrv.
     */
    const LOCK_ADVISORY = 1;
    /**
     * Issues a real row lock. Since it uses a transaction between opening and
     * closing a session, you have to be careful when you use same database connection
     * that you also use for your application logic. This mode is the default because
     * it's the only reliable solution across DBMSs.
     */
    const LOCK_TRANSACTIONAL = 2;

    /**
     * Connects driver to the database.
     */
    public function connect();

    /**
     * Returns whether the PDO instance is connected or not.
     *
     * @return bool
     */
    public function getIsConnected(): bool;

    /**
     * Returns whether the session has expired or not.
     *
     * @return bool
     */
    public function getHasSessionExpired(): bool;

    /**
     * Returns the internal PDO instance.
     *
     * @return PDO
     */
    public function getConnection(): PDO;

    /**
     * Helper method to begin a transaction.
     *
     * Since SQLite does not support row level locks, we have to acquire a reserved lock
     * on the database immediately. Because of https://bugs.php.net/42766 we have to create
     * such a transaction manually which also means we cannot use PDO::commit or
     * PDO::rollback or PDO::inTransaction for SQLite.
     *
     * Also MySQLs default isolation, REPEATABLE READ, causes deadlock for different sessions
     * due to http://www.mysqlperformanceblog.com/2013/12/12/one-more-innodb-gap-lock-to-avoid/ .
     * So we change it to READ COMMITTED.
     */
    public function beginTransaction();

    /**
     * Rollback a transaction (if any).
     */
    public function rollback();

    /**
     * Commits a transaction (if any).
     */
    public function commit();

    /**
     * Reads the session data.
     *
     * @param string $sessionId
     *
     * @return string
     */
    public function read(string $sessionId): string;

    /**
     * Writes the session data
     *
     * @param string $sessionId
     * @param string $data
     *
     * @return bool
     */
    public function write(string $sessionId, string $data): bool;

    /**
     * @param string $sessionId
     *
     * @return PDOStatement
     */
    public function doAdvisoryLocking(string $sessionId): PDOStatement;

    /**
     * Returns the driver's name (ie mysql, sqlite, etc)
     *
     * @return string
     */
    public function getDriver(): string;

    /**
     * Returns the SELECT SQL statement to access the DB.
     *
     * @return string
     */
    public function getSelectSql(): string;

    /**
     * Returns the merge/upsert (i.e. insert or update) statement when supported by the database when writing session
     * data.
     *
     * @param string $sessionId
     * @param string $data
     *
     * @return null|PDOStatement
     */
    public function getMergePdoStatement(string $sessionId, string $data): ?PDOStatement;

    /**
     * Removes the session from database. Used when destroying session.
     *
     * @param string $sessionId
     *
     * @return bool
     */
    public function delete(string $sessionId): bool;

    /**
     * Closes the session ensuring everything is clean.
     *
     * @param bool $gcCalled
     *
     * @return bool
     */
    public function close(bool $gcCalled = false): bool;
}
