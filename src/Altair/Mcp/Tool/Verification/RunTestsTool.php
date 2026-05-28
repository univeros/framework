<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Mcp\Tool\Verification;

use Altair\Doctor\Contracts\ProcessRunnerInterface;
use Altair\Doctor\Process\ShellProcessRunner;
use Altair\Mcp\Attribute\McpTool;
use Altair\Mcp\Contracts\McpToolInterface;
use Altair\Mcp\Guard\PathGuard;
use Altair\Mcp\Support\Output;
use Altair\Mcp\Support\ProjectContext;
use Override;

#[McpTool(
    name: 'framework__run_tests',
    description: 'Run PHPUnit and return a structured pass/fail result.',
    inputSchema: __DIR__ . '/../../Schema/run-tests-input.json',
    outputSchema: __DIR__ . '/../../Schema/object-output.json',
)]
final readonly class RunTestsTool implements McpToolInterface
{
    public function __construct(
        private ProjectContext $context,
        private PathGuard $guard,
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
        $command = ['vendor/bin/phpunit', '--no-output', '--colors=never'];

        if (\is_string($input['filter'] ?? null) && $input['filter'] !== '') {
            $command[] = '--filter';
            $command[] = $input['filter'];
        }

        if (\is_array($input['paths'] ?? null)) {
            foreach ($input['paths'] as $path) {
                if (\is_string($path) && $path !== '') {
                    $this->guard->assertWithinRoot($path);
                    $command[] = $path;
                }
            }
        }

        $result = $this->runner->run($command, $this->context->projectRoot);

        return [
            'passed' => $result->ok(),
            'exit_code' => $result->exitCode,
            'output' => Output::tail($result->stdout . $result->stderr),
        ];
    }
}
