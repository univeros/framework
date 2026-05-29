<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Mcp\Tool\Profile;

use Altair\Mcp\Attribute\McpTool;
use Override;

/**
 * Read-only wrapper over `bin/altair profile:show <id>` — the full report
 * (tree + hotspots + metadata) for one profile.
 */
#[McpTool(
    name: 'framework__profile_show',
    description: 'Show one stored profile by id — full call tree, hotspot table, and metadata.',
    inputSchema: __DIR__ . '/../../Schema/profile-show-input.json',
    outputSchema: __DIR__ . '/../../Schema/object-output.json',
)]
final readonly class ProfileShowTool extends ProfileTool
{
    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function call(array $input): array
    {
        return $this->runProfile('profile:show', ['--', $this->string($input, 'id') ?? '']);
    }
}
