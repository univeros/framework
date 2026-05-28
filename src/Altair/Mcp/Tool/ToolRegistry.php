<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Mcp\Tool;

use Altair\Mcp\Exception\McpException;

/**
 * Holds the set of registered tool descriptors, keyed by MCP name. Listing is
 * deterministic (name-sorted) so `tools/list` output is stable for clients and
 * golden tests.
 */
final class ToolRegistry
{
    /**
     * @var array<string, ToolDescriptor>
     */
    private array $tools = [];

    public function register(ToolDescriptor $descriptor): void
    {
        if (isset($this->tools[$descriptor->name])) {
            throw new McpException(\sprintf("Duplicate MCP tool name '%s'.", $descriptor->name));
        }

        $this->tools[$descriptor->name] = $descriptor;
    }

    public function has(string $name): bool
    {
        return isset($this->tools[$name]);
    }

    public function get(string $name): ToolDescriptor
    {
        return $this->tools[$name]
            ?? throw new McpException(\sprintf("Unknown MCP tool '%s'.", $name));
    }

    /**
     * @return list<ToolDescriptor>
     */
    public function all(): array
    {
        $tools = $this->tools;
        ksort($tools);

        return array_values($tools);
    }

    public function count(): int
    {
        return \count($this->tools);
    }

    /**
     * The `tools/list` payload — one entry per tool, name-sorted.
     *
     * @return list<array<string, mixed>>
     */
    public function listing(): array
    {
        return array_map(
            static fn(ToolDescriptor $descriptor): array => $descriptor->toListEntry(),
            $this->all(),
        );
    }
}
