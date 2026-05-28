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
use Altair\Mcp\Support\Output;
use Altair\Mcp\Support\ProjectContext;
use Override;

#[McpTool(
    name: 'framework__phpstan',
    description: 'Run PHPStan static analysis and return the error count and output.',
    inputSchema: __DIR__ . '/../../Schema/phpstan-input.json',
    outputSchema: __DIR__ . '/../../Schema/object-output.json',
)]
final readonly class PhpstanTool implements McpToolInterface
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
        $command = ['vendor/bin/phpstan', 'analyse', '--no-progress', '--memory-limit=1G', '--error-format=json'];

        if (\is_int($input['level'] ?? null)) {
            $command[] = '--level';
            $command[] = (string) $input['level'];
        }

        $result = $this->runner->run($command, $this->context->projectRoot);

        return [
            'passed' => $result->ok(),
            'exit_code' => $result->exitCode,
            'errors' => $this->errorCount($result->stdout),
            'output' => Output::tail($result->stdout . $result->stderr),
        ];
    }

    private function errorCount(string $json): ?int
    {
        $decoded = json_decode($json, true);
        if (!\is_array($decoded)) {
            return null;
        }

        $total = $decoded['totals']['file_errors'] ?? null;

        return \is_int($total) ? $total : null;
    }
}
