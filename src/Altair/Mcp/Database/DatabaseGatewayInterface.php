<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Mcp\Database;

/**
 * Read access to the project's development database for the db_* tools. The
 * default binding is {@see NullDatabaseGateway} (no DB); when the persistence
 * package is wired, {@see CycleDatabaseGateway} adapts Cycle's DBAL.
 */
interface DatabaseGatewayInterface
{
    public function isConfigured(): bool;

    /**
     * Run a read-only SELECT (already validated by {@see SqlReadGuard}).
     *
     * @return list<array<string, mixed>>
     */
    public function select(string $sql): array;

    /**
     * Describe the current schema: one entry per table with its columns.
     *
     * @return list<array{table: string, columns: list<array{name: string, type: string}>}>
     */
    public function schema(): array;
}
