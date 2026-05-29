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
 * Read-only wrapper over `bin/altair profile:list` — newest profiles first.
 */
#[McpTool(
    name: 'framework__profile_list',
    description: 'List captured profiles under .altair/profiles/, newest first (id, target, timestamp, sample count).',
    inputSchema: __DIR__ . '/../../Schema/profile-list-input.json',
    outputSchema: __DIR__ . '/../../Schema/object-output.json',
)]
final readonly class ProfileListTool extends ProfileTool
{
    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function call(array $input): array
    {
        $arguments = [];
        if (\is_int($input['limit'] ?? null)) {
            $arguments[] = '--limit=' . $input['limit'];
        }

        return $this->runProfile('profile:list', $arguments);
    }
}
