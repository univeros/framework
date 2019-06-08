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
use Error;
use PDO;
use PDOStatement;

/**
 *
 * Session table must have the following structure:
 *
 * CREATE TABLE $this->table
 * (
 *      id TEXT NOT NULL PRIMARY KEY,
 *      content BLOB NOT NULL,
 *      session_lifetime INTEGER NOT NULL,
 *      session_time INTEGER NOT NULL
 * )
 */
class SqlitePdoSessionAdapter implements PdoSessionAdapterInterface
{
    use PdoSessionAdapterAwareTrait;

    /**
     * @inheritDoc
     */
    public function doAdvisoryLocking(string $sessionId): PDOStatement
    {
        throw new Error('SQLite does not support advisory locks.');
    }

    /**
     * @inheritDoc
     */
    public function getDriver(): string
    {
        return self::DRIVER_SQLITE;
    }

    /**
     * @inheritDoc
     */
    public function getSelectSql(): string
    {
        $sql = 'SELECT content, session_lifetime, session_time FROM %s WHERE id = :id';

        return sprintf($sql, $this->table);
    }

    /**
     * @inheritDoc
     */
    public function getMergePdoStatement(string $sessionId, string $data): ?PDOStatement
    {
        $maxlifetime = (int)ini_get('session.gc_maxlifetime');

        $sql = sprintf(
            'INSERT OR REPLACE INTO %s (id, content, session_lifetime, session_time) '.
            'VALUES (:id, :content, :lifetime, :session_time)',
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
