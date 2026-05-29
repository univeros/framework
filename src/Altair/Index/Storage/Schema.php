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
 * The SQLite schema for the symbol-usage index.
 *
 * Usages store the target symbol's fully-qualified name directly (rather than a
 * foreign key into `symbols`) so a reference to an undeclared or vendor symbol
 * is still recorded and queryable. `files` tracks content hashes so an
 * incremental rebuild can skip files that have not changed.
 */
final class Schema
{
    public const array STATEMENTS = [
        'CREATE TABLE IF NOT EXISTS symbols (
            id INTEGER PRIMARY KEY,
            fqn TEXT NOT NULL UNIQUE,
            kind TEXT NOT NULL,
            file TEXT NOT NULL,
            line INTEGER NOT NULL,
            visibility TEXT,
            is_readonly INTEGER NOT NULL DEFAULT 0,
            is_static INTEGER NOT NULL DEFAULT 0
        )',
        'CREATE TABLE IF NOT EXISTS usages (
            id INTEGER PRIMARY KEY,
            target_fqn TEXT NOT NULL,
            used_in_file TEXT NOT NULL,
            used_in_line INTEGER NOT NULL,
            usage_kind TEXT NOT NULL,
            context TEXT
        )',
        'CREATE TABLE IF NOT EXISTS files (
            path TEXT PRIMARY KEY,
            hash TEXT NOT NULL
        )',
        'CREATE TABLE IF NOT EXISTS meta (
            key TEXT PRIMARY KEY,
            value TEXT
        )',
        'CREATE INDEX IF NOT EXISTS usages_by_target ON usages(target_fqn)',
        'CREATE INDEX IF NOT EXISTS usages_by_kind ON usages(usage_kind)',
        'CREATE INDEX IF NOT EXISTS symbols_by_kind ON symbols(kind)',
        'CREATE INDEX IF NOT EXISTS symbols_by_file ON symbols(file)',
        'CREATE INDEX IF NOT EXISTS usages_by_file ON usages(used_in_file)',
    ];

    public static function create(PDO $pdo): void
    {
        foreach (self::STATEMENTS as $statement) {
            $pdo->exec($statement);
        }
    }
}
