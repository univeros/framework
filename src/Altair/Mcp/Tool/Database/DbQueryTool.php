<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Mcp\Tool\Database;

use Altair\Mcp\Attribute\McpTool;
use Altair\Mcp\Contracts\McpToolInterface;
use Altair\Mcp\Database\DatabaseGatewayInterface;
use Altair\Mcp\Database\SqlReadGuard;
use Altair\Mcp\Exception\McpException;
use Override;

#[McpTool(
    name: 'framework__db_query',
    description: 'Run a read-only SELECT against the development database and return the rows.',
    inputSchema: __DIR__ . '/../../Schema/db-query-input.json',
    outputSchema: __DIR__ . '/../../Schema/object-output.json',
)]
final readonly class DbQueryTool implements McpToolInterface
{
    public function __construct(
        private DatabaseGatewayInterface $database,
        private SqlReadGuard $guard = new SqlReadGuard(),
    ) {}

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function call(array $input): array
    {
        $sql = \is_string($input['sql'] ?? null) ? $input['sql'] : '';
        if ($sql === '') {
            throw new McpException("'sql' is required.");
        }

        $this->guard->assertReadOnly($sql);
        $rows = $this->database->select($sql);

        return ['rows' => $rows, 'count' => \count($rows)];
    }
}
