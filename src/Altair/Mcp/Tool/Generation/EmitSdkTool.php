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
use Altair\Mcp\Exception\McpException;
use Altair\Mcp\Support\OpenApiFragments;
use Altair\Scaffold\Sdk\EmitterRegistry;
use Altair\Scaffold\Sdk\Model\OpenApiParser;
use Override;

#[McpTool(
    name: 'framework__emit_sdk',
    description: 'Generate a typed client SDK (typescript or python) from the OpenAPI document.',
    inputSchema: __DIR__ . '/../../Schema/emit-sdk-input.json',
    outputSchema: __DIR__ . '/../../Schema/object-output.json',
)]
final readonly class EmitSdkTool implements McpToolInterface
{
    public function __construct(
        private OpenApiFragments $fragments,
        private OpenApiParser $parser = new OpenApiParser(),
    ) {}

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function call(array $input): array
    {
        $language = \is_string($input['language'] ?? null) ? $input['language'] : '';
        $registry = EmitterRegistry::default();

        if (!$registry->has($language)) {
            throw new McpException(\sprintf(
                "Unknown SDK language '%s'. Available: %s.",
                $language,
                implode(', ', $registry->available()),
            ));
        }

        if (!$this->fragments->exists()) {
            throw new McpException('No docs/openapi fragments in this project; scaffold an endpoint first.');
        }

        $document = $this->parser->parseYaml($this->fragments->mergeYaml());
        $emitted = $registry->get($language)->emit($document, false);

        return ['language' => $language, 'files' => $emitted->files];
    }
}
