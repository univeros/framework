<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Session\Adapter;

use Altair\Session\Contracts\PdoSessionAdapterInterface;
use Altair\Session\Traits\PdoSessionAdapterAwareTrait;
use PDO;
use PDOStatement;

/**
 *
 * Session table must have the following structure:
 *
 * CREATE TABLE $this->table
 * (
 *      id VARCHAR(128) NOT NULL PRIMARY KEY,
 *      content BYTEA NOT NULL,
 *      session_lifetime INTEGER NOT NULL,
 *      session_time INTEGER NOT NULL
 * )
 */
class PostgreSqlPdoSessionAdapter implements PdoSessionAdapterInterface
{
    use PdoSessionAdapterAwareTrait;

    /**
     * @inheritDoc
     */
    public function doAdvisoryLocking(string $sessionId): PDOStatement
    {
        // Obtaining an exclusive session level advisory lock requires an integer key.
        // So we convert the HEX representation of the session id to an integer.
        // Since integers are signed, we have to skip one hex char to fit in the range.
        // - Thanks @symfony
        if (4 === PHP_INT_SIZE) {
            $sessionInt1 = hexdec(substr($sessionId, 0, 7));
            $sessionInt2 = hexdec(substr($sessionId, 7, 7));
            $query = $this->getConnection()->prepare('SELECT pg_advisory_lock(:key1, :key2)');
            $query->bindValue(':key1', $sessionInt1, PDO::PARAM_INT);
            $query->bindValue(':key2', $sessionInt2, PDO::PARAM_INT);
            $query->execute();
            $releaseQuery = $this->getConnection()->prepare('SELECT pg_advisory_unlock(:key1, :key2)');
            $releaseQuery->bindValue(':key1', $sessionInt1, PDO::PARAM_INT);
            $releaseQuery->bindValue(':key2', $sessionInt2, PDO::PARAM_INT);
        } else {
            $sessionBigInt = hexdec(substr($sessionId, 0, 15));
            $query = $this->getConnection()->prepare('SELECT pg_advisory_lock(:key)');
            $query->bindValue(':key', $sessionBigInt, PDO::PARAM_INT);
            $query->execute();
            $releaseQuery = $this->getConnection()->prepare('SELECT pg_advisory_unlock(:key)');
            $releaseQuery->bindValue(':key', $sessionBigInt, PDO::PARAM_INT);
        }

        return $releaseQuery;
    }

    /**
     * @inheritDoc
     */
    public function getDriver(): string
    {
        return self::DRIVER_POSTGRESQL;
    }

    /**
     * @inheritDoc
     */
    public function getSelectSql(): string
    {
        $sql = $this->getIsLockModeTransactional()
            ? 'SELECT content, session_lifetime, session_time FROM %s WHERE id = :id FOR UPDATE'
            : 'SELECT content, session_lifetime, session_time FROM %s WHERE id = :id';

        return sprintf($sql, $this->table);
    }

    /**
     * @inheritDoc
     */
    public function getMergePdoStatement(string $sessionId, string $data): ?PDOStatement
    {
        if (version_compare($this->pdo->getAttribute(\PDO::ATTR_SERVER_VERSION), '9.5', '>=')) {
            $maxlifetime = (int)ini_get('session.gc_maxlifetime');
            $sql = sprintf(
                'INSERT INTO %s (id, content, session_lifetime, session_time) ' .
                'VALUES (:id, :content, :lifetime, :session_time) ' .
                'ON CONFLICT (id) DO UPDATE SET (id, lifetime, session_time) = ' .
                '(EXCLUDED.content, EXCLUDED.lifetime, EXCLUDED.session_time)',
                $this->table
            );

            $query = $this->getConnection()->prepare($sql);
            $query->bindParam(':id', $sessionId, PDO::PARAM_STR);
            $query->bindParam(':content', $data, PDO::PARAM_STR);
            $query->bindParam(':lifetime', $maxlifetime, PDO::PARAM_INT);
            $query->bindValue(':session_time', time(), PDO::PARAM_INT);

            return $query;
        }

        return null;
    }
}
