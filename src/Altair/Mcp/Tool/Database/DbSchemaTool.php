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
use Override;

#[McpTool(
    name: 'framework__db_schema',
    description: 'Dump the current database schema: tables with their columns.',
    inputSchema: __DIR__ . '/../../Schema/no-args.json',
    outputSchema: __DIR__ . '/../../Schema/object-output.json',
)]
final readonly class DbSchemaTool implements McpToolInterface
{
    public function __construct(private DatabaseGatewayInterface $database) {}

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function call(array $input): array
    {
        $tables = $this->database->schema();

        return ['tables' => $tables, 'count' => \count($tables)];
    }
}
