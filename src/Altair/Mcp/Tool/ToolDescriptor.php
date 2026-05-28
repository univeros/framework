<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Mcp\Tool;

/**
 * Resolved metadata for one registered tool: its MCP name, description, the
 * implementing class, and the decoded input/output JSON schemas.
 */
final readonly class ToolDescriptor
{
    /**
     * @param class-string              $className
     * @param array<string, mixed>|null $inputSchema
     * @param array<string, mixed>|null $outputSchema
     */
    public function __construct(
        public string $name,
        public string $description,
        public string $className,
        public ?array $inputSchema = null,
        public ?array $outputSchema = null,
    ) {}

    /**
     * The entry published through the MCP `tools/list` method. `inputSchema`
     * always defaults to an open object so clients have something to render.
     *
     * @return array<string, mixed>
     */
    public function toListEntry(): array
    {
        $entry = [
            'name' => $this->name,
            'description' => $this->description,
            'inputSchema' => $this->inputSchema ?? ['type' => 'object'],
        ];

        if ($this->outputSchema !== null) {
            $entry['outputSchema'] = $this->outputSchema;
        }

        return $entry;
    }
}
