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
 * Read-only wrapper over `bin/altair profile:compare`. The refactor
 * confidence loop's killer move: profile before, change, profile after,
 * compare. Returns the structured diff with regressions flagged.
 */
#[McpTool(
    name: 'framework__profile_compare',
    description: 'Diff two stored profiles function-by-function. Reports regressions (>= 10% self-time on functions with >= 5 head samples) and improvements.',
    inputSchema: __DIR__ . '/../../Schema/profile-compare-input.json',
    outputSchema: __DIR__ . '/../../Schema/object-output.json',
)]
final readonly class ProfileCompareTool extends ProfileTool
{
    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function call(array $input): array
    {
        $base = $this->string($input, 'base_id') ?? '';
        $head = $this->string($input, 'head_id') ?? '';

        return $this->runProfile('profile:compare', ['--', $base, $head]);
    }
}
