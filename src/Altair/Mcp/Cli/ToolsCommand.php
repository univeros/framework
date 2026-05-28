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
use Altair\Mcp\Tool\ToolRegistry;

use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_SLASHES;
use const STDERR;

/**
 * `bin/altair mcp:tools` — list the tools this MCP server exposes. A debug aid
 * so you can see the palette without wiring up a client.
 */
#[Command(name: 'mcp:tools', description: 'List the MCP tools this server exposes.')]
final readonly class ToolsCommand
{
    public function __construct(private Container $container) {}

    public function __invoke(
        #[Option(description: 'Output format: text or json.')]
        string $format = 'text',
        #[Option(description: 'Override the project root.')]
        ?string $root = null,
    ): int {
        (new McpConfiguration(projectRoot: $root))->apply($this->container);

        $registry = $this->container->make(ToolRegistry::class);
        if (!$registry instanceof ToolRegistry) {
            fwrite(STDERR, "Failed to build the tool registry.\n");

            return 1;
        }

        if ($format === 'json') {
            echo json_encode($registry->listing(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";

            return 0;
        }

        foreach ($registry->all() as $descriptor) {
            echo \sprintf("%-32s %s\n", $descriptor->name, $descriptor->description);
        }

        echo \sprintf("\n%d tools.\n", $registry->count());

        return 0;
    }
}
