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
 * Read-only wrapper over `bin/altair index:implements` — the classes that
 * declare `implements <interface>`.
 */
#[McpTool(
    name: 'framework__implementers',
    description: 'List the classes that implement a given interface.',
    inputSchema: __DIR__ . '/../../Schema/implementers-input.json',
    outputSchema: __DIR__ . '/../../Schema/object-output.json',
)]
final readonly class ImplementersTool extends IndexTool
{
    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function call(array $input): array
    {
        return $this->runIndex('index:implements', [$this->string($input, 'interface') ?? '']);
    }
}
