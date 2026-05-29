<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Mcp\Tool\Profile;

use Altair\Doctor\Contracts\ProcessRunnerInterface;
use Altair\Doctor\Process\ShellProcessRunner;
use Altair\Mcp\Attribute\McpTool;
use Altair\Mcp\Contracts\McpToolInterface;
use Altair\Mcp\Support\Output;
use Altair\Mcp\Support\ProjectContext;
use Override;

/**
 * Read-only wrapper over `bin/altair profile:flame <id>`. Returns the SVG
 * flamegraph for a stored profile so an agent can hand it to a renderer or
 * paste it inline. Output is the raw SVG inside an `{ ok, svg }` envelope.
 */
#[McpTool(
    name: 'framework__profile_flame',
    description: 'Render a stored profile as an inline-SVG flamegraph. Returns the SVG source so it can be saved or pasted inline.',
    inputSchema: __DIR__ . '/../../Schema/profile-show-input.json',
    outputSchema: __DIR__ . '/../../Schema/object-output.json',
)]
final readonly class ProfileFlameTool implements McpToolInterface
{
    public function __construct(
        private ProjectContext $context,
        private ProcessRunnerInterface $runner = new ShellProcessRunner(),
    ) {}

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function call(array $input): array
    {
        $id = \is_string($input['id'] ?? null) ? $input['id'] : '';

        $result = $this->runner->run(['php', 'bin/altair', 'profile:flame', '--', $id], $this->context->projectRoot);

        if ($result->exitCode !== 0) {
            return [
                'ok' => false,
                'exit_code' => $result->exitCode,
                'error' => Output::tail($result->stdout . $result->stderr),
            ];
        }

        return [
            'ok' => true,
            'svg' => $result->stdout,
        ];
    }
}
