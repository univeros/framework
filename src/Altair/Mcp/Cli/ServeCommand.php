<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Mcp\Cli;

use Altair\Cli\Attribute\Command;
use Altair\Cli\Attribute\Option;
use Altair\Container\Container;
use Altair\Mcp\Configuration\McpConfiguration;
use Altair\Mcp\Guard\ServerMode;
use Altair\Mcp\Server\Server;
use Altair\Mcp\Server\ServerRunner;
use Altair\Mcp\Transport\HttpTransport;
use Altair\Mcp\Transport\StdioTransport;

use const STDERR;

/**
 * `bin/altair mcp:serve` — start the Model Context Protocol server so an
 * MCP-capable agent (Claude Desktop, Cursor, ...) can drive this project.
 *
 * Defaults to the stdio transport (what desktop clients expect). Pass
 * `--transport=http` for an out-of-process scenario. `--readonly` makes the
 * whole server inspect-only; `--allow-writes` opts into gated DB migrations.
 */
#[Command(name: 'mcp:serve', description: 'Start the MCP server (stdio by default).')]
final readonly class ServeCommand
{
    public function __construct(private Container $container) {}

    public function __invoke(
        #[Option(description: 'Transport to use: stdio or http.')]
        string $transport = 'stdio',
        #[Option(description: 'Host for the HTTP transport.')]
        string $host = '127.0.0.1',
        #[Option(description: 'Port for the HTTP transport.')]
        int $port = 3737,
        #[Option(description: 'Inspect-only mode: block every write.')]
        bool $readonly = false,
        #[Option(description: 'Allow gated database writes (migrations).', name: 'allow-writes')]
        bool $allowWrites = false,
        #[Option(description: 'Override the project root.')]
        ?string $root = null,
    ): int {
        $mode = new ServerMode(readonly: $readonly, allowWrites: $allowWrites);
        (new McpConfiguration(projectRoot: $root, mode: $mode))->apply($this->container);

        $server = $this->container->make(Server::class);
        if (!$server instanceof Server) {
            fwrite(STDERR, "Failed to build the MCP server.\n");

            return 1;
        }

        if ($transport === 'http') {
            return (new HttpTransport($server))->serve($host, $port);
        }

        if ($transport !== 'stdio') {
            fwrite(STDERR, \sprintf("Unknown transport '%s'. Use stdio or http.\n", $transport));

            return 2;
        }

        (new ServerRunner($server, new StdioTransport()))->run();

        return 0;
    }
}
