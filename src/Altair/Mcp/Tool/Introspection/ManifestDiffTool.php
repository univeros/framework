<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Mcp\Tool\Introspection;

use Altair\Introspection\Exception\IntrospectionException;
use Altair\Introspection\Inspector\ManifestDiffInspector;
use Altair\Mcp\Attribute\McpTool;
use Altair\Mcp\Contracts\McpToolInterface;
use Altair\Mcp\Support\ProjectContext;
use Override;

#[McpTool(
    name: 'framework__manifest_diff',
    description: 'Report drift between the on-disk .agent/ manifests and a fresh regeneration.',
    inputSchema: __DIR__ . '/../../Schema/no-args.json',
    outputSchema: __DIR__ . '/../../Schema/object-output.json',
)]
final readonly class ManifestDiffTool implements McpToolInterface
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
        $manifestRoot = $this->context->path('.agent');
        if (!is_dir($manifestRoot)) {
            return ['available' => false, 'note' => 'No .agent/ manifest directory in this project.'];
        }

        try {
            // No regenerator wired here: the on-disk tree is treated as canonical,
            // mirroring `bin/altair manifest:diff`'s default behaviour.
            return (new ManifestDiffInspector($manifestRoot))->diff([])->toArray();
        } catch (IntrospectionException $introspectionException) {
            return ['available' => false, 'note' => $introspectionException->getMessage()];
        }
    }
}
