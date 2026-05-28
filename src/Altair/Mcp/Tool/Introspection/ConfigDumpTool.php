<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Mcp\Tool\Introspection;

use Altair\Container\Container;
use Altair\Introspection\Inspector\ConfigInspector;
use Altair\Mcp\Attribute\McpTool;
use Altair\Mcp\Contracts\McpToolInterface;
use Override;

#[McpTool(
    name: 'framework__config_dump',
    description: 'Dump merged env + container parameters with secrets masked.',
    inputSchema: __DIR__ . '/../../Schema/no-args.json',
    outputSchema: __DIR__ . '/../../Schema/object-output.json',
)]
final readonly class ConfigDumpTool implements McpToolInterface
{
    /**
     * Extra name patterns masked at the agent boundary, on top of
     * {@see ConfigInspector::DEFAULT_SECRET_PATTERNS} — the MCP surface masks
     * more aggressively than the CLI since output goes to an LLM context.
     */
    private const array EXTRA_SECRET_PATTERNS = ['PASS', 'PWD', 'OAUTH', 'DSN', 'SALT', 'CERT', 'SIGNATURE', 'WEBHOOK'];

    public function __construct(private Container $container) {}

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function call(array $input): array
    {
        // Secrets are masked unconditionally at the MCP boundary — the caller
        // cannot opt into raw secret values.
        return (new ConfigInspector($this->container, self::EXTRA_SECRET_PATTERNS))->dump(maskSecrets: true)->toArray();
    }
}
