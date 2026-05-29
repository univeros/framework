<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Mcp\Tool\Observability;

use Altair\Doctor\Contracts\ProcessRunnerInterface;
use Altair\Doctor\Process\ShellProcessRunner;
use Altair\Mcp\Attribute\McpTool;
use Altair\Mcp\Contracts\McpToolInterface;
use Altair\Mcp\Support\Output;
use Altair\Mcp\Support\ProjectContext;
use Override;

/**
 * Read-only wrapper over `bin/altair observability:tail` — recent spans and
 * metric points from the JSONL log.
 */
#[McpTool(
    name: 'framework__observability_tail',
    description: 'Tail the most recent spans and metric points from the observability JSONL log (.altair/observability/). Filter by kind=span|metric.',
    inputSchema: __DIR__ . '/../../Schema/observability-tail-input.json',
    outputSchema: __DIR__ . '/../../Schema/object-output.json',
)]
final readonly class ObservabilityTailTool implements McpToolInterface
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
        $command = ['php', 'bin/altair', 'observability:tail', '--format=json'];

        if (\is_int($input['limit'] ?? null)) {
            $command[] = '--limit=' . $input['limit'];
        }

        if (\is_string($input['kind'] ?? null) && \in_array($input['kind'], ['span', 'metric'], true)) {
            $command[] = '--kind=' . $input['kind'];
        }

        $result = $this->runner->run($command, $this->context->projectRoot);
        $decoded = json_decode($result->stdout, true);
        if (\is_array($decoded)) {
            return ['ok' => $result->exitCode === 0, ...$decoded];
        }

        return [
            'ok' => false,
            'exit_code' => $result->exitCode,
            'error' => Output::tail($result->stdout . $result->stderr),
        ];
    }
}
