<?php
namespace Altair\Session\Adapter;

use Altair\Session\Contracts\PdoSessionAdapterInterface;
use Altair\Session\Traits\PdoSessionAdapterAwareTrait;
use PDO;
use PDOStatement;

/**
 *
 * Session table must have the following structure:
 *
 * CREATE TABLE $this->TABLE
 * (
 *      id VARBINARY(128) NOT NULL PRIMARY KEY,
 *      content BLOB NOT NULL,
 *      session_lifetime MEDIUMINT NOT NULL,
 *      session_time INTEGER UNSIGNED NOT NULL
 * )
 * COLLATE utf8_bin, engine = innodb
 */
class MySqlPdoSessionAdapter implements PdoSessionAdapterInterface
{
    use PdoSessionAdapterAwareTrait;

    /**
     * @inheritdoc
     */
    public function doAdvisoryLocking(string $sessionId): PDOStatement
    {
        // should we handle the return value? 0 on timeout, null on error
        // we use a timeout of 50 seconds which is also the default for innodb_lock_wait_timeout
        // - Thanks @symfony
        $query = $this->getConnection()->prepare('SELECT GET_LOCK(:key, 50)');
        $query->bindValue(':key', $sessionId, PDO::PARAM_STR);
        $query->execute();

        $releaseQuery = $this->getConnection()->prepare('DO RELEASE_LOCK(:key)');
        $releaseQuery->bindValue(':key', $sessionId, PDO::PARAM_STR);

        return $releaseQuery;
    }

    /**
     * @inheritdoc
     */
    public function getDriver(): string
    {
        return self::DRIVER_MYSQL;
    }

    /**
     * @inheritdoc
     */
    public function getSelectSql(): string
    {
        $sql = $this->getIsLockModeTransactional()
            ? 'SELECT content, session_lifetime, session_time FROM %s WHERE id = :id FOR UPDATE'
            : 'SELECT content, session_lifetime, session_time FROM %s WHERE id = :id';

        return sprintf($sql, $this->table);
    }

    /**
     * @inheritdoc
     */
    public function getMergePdoStatement(string $sessionId, string $data): ?PDOStatement
    {
        $maxlifetime = (int)ini_get('session.gc_maxlifetime');

        $sql = sprintf(
            'INSERT INTO %s (id, content, session_lifetime, session_time) ' .
            'VALUES (:id, :content, :lifetime, :session_time) ON DUPLICATE KEY UPDATE ' .
            'content=VALUES(content), session_lifetime=VALUES(session_lifetime), session_time=VALUES(session_time)',
            $this->table
        );

        $query = $this->getConnection()->prepare($sql);
        $query->bindParam(':id', $sessionId, PDO::PARAM_STR);
        $query->bindParam(':content', $data, PDO::PARAM_STR);
        $query->bindParam(':lifetime', $maxlifetime, PDO::PARAM_INT);
        $query->bindValue(':session_time', time(), PDO::PARAM_INT);

        return $query;
    }
}
