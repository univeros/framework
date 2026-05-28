<?php

declare(strict_types=1);

namespace Altair\Tests\Mcp\Fixtures;

use Altair\Mcp\Attribute\McpTool;
use Altair\Mcp\Contracts\McpToolInterface;
use Altair\Mcp\Exception\GuardrailException;
use Override;
use RuntimeException;

#[McpTool(name: 'test__failing', description: 'Always fails.')]
final class FailingTool implements McpToolInterface
{
    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function call(array $input): array
    {
        if (($input['guardrail'] ?? false) === true) {
            throw new GuardrailException('Refusing to write to vendor/.');
        }

        throw new RuntimeException('boom');
    }
}
