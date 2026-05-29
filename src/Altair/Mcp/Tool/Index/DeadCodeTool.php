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
 * Read-only wrapper over `bin/altair index:unused` — symbols with zero recorded
 * references. These are dead-code *candidates*: framework entry points reached
 * by dispatch the AST can't see still appear, so the agent should confirm
 * before deleting.
 */
#[McpTool(
    name: 'framework__dead_code',
    description: 'List symbols with zero recorded usages (dead-code candidates; review before deleting).',
    inputSchema: __DIR__ . '/../../Schema/no-args.json',
    outputSchema: __DIR__ . '/../../Schema/object-output.json',
)]
final readonly class DeadCodeTool extends IndexTool
{
    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function call(array $input): array
    {
        return $this->runIndex('index:unused');
    }
}
