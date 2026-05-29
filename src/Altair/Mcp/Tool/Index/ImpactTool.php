<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Mcp\Tool\Index;

use Altair\Mcp\Attribute\McpTool;
use Override;

/**
 * Read-only wrapper over `bin/altair index:impact` — the refactor blast radius
 * of changing a set of symbols. Returns affected file/test/spec counts plus the
 * concrete `tests_to_run` and `specs_affected`, so an agent can run only what a
 * change touches before declaring it done.
 */
#[McpTool(
    name: 'framework__impact',
    description: 'Report the files, tests, and specs affected by changing a set of symbols (the key refactor-confidence tool).',
    inputSchema: __DIR__ . '/../../Schema/impact-input.json',
    outputSchema: __DIR__ . '/../../Schema/object-output.json',
)]
final readonly class ImpactTool extends IndexTool
{
    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function call(array $input): array
    {
        $symbols = $input['symbols'] ?? [];
        if (!\is_array($symbols)) {
            $symbols = [];
        }

        $list = array_values(array_filter(
            array_map(static fn(mixed $s): string => \is_string($s) ? $s : '', $symbols),
            static fn(string $s): bool => $s !== '',
        ));

        return $this->runIndex('index:impact', [implode(',', $list)]);
    }
}
