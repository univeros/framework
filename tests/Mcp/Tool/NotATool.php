<?php

declare(strict_types=1);

namespace Altair\Tests\Mcp\Tool;

use Altair\Mcp\Attribute\McpTool;

/**
 * Carries #[McpTool] but deliberately omits McpToolInterface — the discoverer
 * must reject it.
 */
#[McpTool(name: 'bad__tool', description: 'Not a real tool.')]
final class NotATool
{
}
