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
 * Read-some wrapper over `bin/altair profile:run`. Profiles a PHP script in a
 * subprocess with the sampling backend attached, saves the report under
 * .altair/profiles/, and returns the structured report.
 */
#[McpTool(
    name: 'framework__profile',
    description: 'Profile a PHP script under the sampling profiler (ext-excimer). Saves the report to .altair/profiles/ and returns it.',
    inputSchema: __DIR__ . '/../../Schema/profile-run-input.json',
    outputSchema: __DIR__ . '/../../Schema/object-output.json',
)]
final readonly class ProfileRunTool extends ProfileTool
{
    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function call(array $input): array
    {
        $script = $this->string($input, 'script') ?? '';

        $arguments = [];
        if (($description = $this->string($input, 'description')) !== null) {
            $arguments[] = '--description=' . $description;
        }

        if (\is_int($input['period_us'] ?? null)) {
            $arguments[] = '--period-us=' . $input['period_us'];
        }

        if (\is_int($input['timeout_ms'] ?? null)) {
            $arguments[] = '--timeout-ms=' . $input['timeout_ms'];
        }

        $arguments[] = '--';
        $arguments[] = $script;

        return $this->runProfile('profile:run', $arguments);
    }
}
