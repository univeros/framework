<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Index\Storage;

use PDO;

/**
 * Opens a configured PDO SQLite connection for the index database.
 *
 * Pass `:memory:` for an ephemeral database (tests); pass a filesystem path for
 * the persisted `.altair/index.db`. The parent directory is created on demand.
 */
final class Connection
{
    public static function open(string $path): PDO
    {
        if ($path !== ':memory:') {
            $directory = \dirname($path);
            if (!is_dir($directory)) {
                mkdir($directory, 0o755, true);
            }
        }

        $dsn = $path === ':memory:' ? 'sqlite::memory:' : 'sqlite:' . $path;
        $pdo = new PDO($dsn);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA journal_mode = WAL');

        return $pdo;
    }
}
