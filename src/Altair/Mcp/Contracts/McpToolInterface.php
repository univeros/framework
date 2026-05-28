<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Mcp\Contracts;

/**
 * An MCP tool: a named, JSON-in/JSON-out operation the agent can call.
 *
 * Metadata (name, description, schemas) lives on the {@see \Altair\Mcp\Attribute\McpTool}
 * attribute, not here, so a tool is a plain service the Container can autowire.
 * Input has already been validated against the tool's input schema before
 * {@see call()} runs. Implementations return a JSON-serialisable array.
 */
interface McpToolInterface
{
    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    public function call(array $input): array;
}
