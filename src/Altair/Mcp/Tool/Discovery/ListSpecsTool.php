<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Mcp\Tool\Discovery;

use Altair\Introspection\Inspector\SpecInspector;
use Altair\Mcp\Attribute\McpTool;
use Altair\Mcp\Contracts\McpToolInterface;
use Altair\Mcp\Support\ProjectContext;
use Override;

#[McpTool(
    name: 'framework__list_specs',
    description: 'List every YAML endpoint spec under the project api/ directory.',
    inputSchema: __DIR__ . '/../../Schema/no-args.json',
    outputSchema: __DIR__ . '/../../Schema/object-output.json',
)]
final readonly class ListSpecsTool implements McpToolInterface
{
    public function __construct(private ProjectContext $context) {}

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function call(array $input): array
    {
        $specRoot = $this->context->path('api');
        if (!is_dir($specRoot)) {
            return ['specs' => [], 'count' => 0, 'note' => 'No api/ directory in this project.'];
        }

        return (new SpecInspector($specRoot))->inspectAll()->toArray();
    }
}
