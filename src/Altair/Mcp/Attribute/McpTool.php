<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Mcp\Attribute;

use Attribute;

/**
 * Marks a class as an MCP tool. The discoverer reads this attribute to build a
 * {@see \Altair\Mcp\Tool\ToolDescriptor}; the class itself implements
 * {@see \Altair\Mcp\Contracts\McpToolInterface}.
 *
 * `inputSchema` / `outputSchema` are absolute paths to JSON Schema files
 * (typically `__DIR__ . '/Schema/<tool>-input.json'`). They are published
 * verbatim through the MCP `tools/list` method so clients can validate calls.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class McpTool
{
    public function __construct(
        public string $name,
        public string $description,
        public ?string $inputSchema = null,
        public ?string $outputSchema = null,
    ) {}
}
