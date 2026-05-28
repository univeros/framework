<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Mcp\Database;

use Altair\Mcp\Exception\McpException;
use Override;

/**
 * Default gateway for projects without a wired database: every access reports
 * that no database is configured rather than failing obscurely.
 */
final readonly class NullDatabaseGateway implements DatabaseGatewayInterface
{
    #[Override]
    public function isConfigured(): bool
    {
        return false;
    }

    /**
     * @return list<array<string, mixed>>
     */
    #[Override]
    public function select(string $sql): array
    {
        throw $this->unconfigured();
    }

    /**
     * @return list<array{table: string, columns: list<array{name: string, type: string}>}>
     */
    #[Override]
    public function schema(): array
    {
        throw $this->unconfigured();
    }

    private function unconfigured(): McpException
    {
        return new McpException(
            'No database is configured for the MCP server. Set DB_* env vars and wire univeros/persistence.',
        );
    }
}
