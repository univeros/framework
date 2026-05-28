<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Mcp\Tool\Generation;

use Altair\Mcp\Attribute\McpTool;
use Altair\Mcp\Contracts\McpToolInterface;
use Altair\Mcp\Support\OpenApiFragments;
use Override;

#[McpTool(
    name: 'framework__emit_openapi',
    description: 'Merge the per-endpoint OpenAPI fragments into a single OpenAPI 3.1 document.',
    inputSchema: __DIR__ . '/../../Schema/no-args.json',
    outputSchema: __DIR__ . '/../../Schema/object-output.json',
)]
final readonly class EmitOpenApiTool implements McpToolInterface
{
    public function __construct(private OpenApiFragments $fragments) {}

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function call(array $input): array
    {
        if (!$this->fragments->exists()) {
            return ['openapi' => null, 'note' => 'No docs/openapi fragments in this project.'];
        }

        return ['document' => $this->fragments->merge()];
    }
}
