<?php

declare(strict_types=1);

namespace Altair\Tests\Mcp\Fixtures;

use Altair\Mcp\Attribute\McpTool;
use Altair\Mcp\Contracts\McpToolInterface;
use Override;

#[McpTool(
    name: 'test__echo',
    description: 'Echo the input back to the caller.',
    inputSchema: __DIR__ . '/echo-input.json',
)]
final class EchoTool implements McpToolInterface
{
    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function call(array $input): array
    {
        return ['echo' => $input['message'] ?? null];
    }
}
