<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Mcp\Tool\Index;

use Altair\Doctor\Contracts\ProcessRunnerInterface;
use Altair\Doctor\Process\ShellProcessRunner;
use Altair\Mcp\Contracts\McpToolInterface;
use Altair\Mcp\Support\Output;
use Altair\Mcp\Support\ProjectContext;

/**
 * Shared base for the read-only symbol-index tools.
 *
 * Each wraps a `bin/altair index:* --format=json` invocation in the host
 * project. The CLI auto-rebuilds the index incrementally before answering, so
 * these tools always reflect the current source without a separate build step.
 * The decoded CLI payload is returned as-is with an added `ok` flag.
 */
abstract readonly class IndexTool implements McpToolInterface
{
    public function __construct(
        protected ProjectContext $context,
        protected ProcessRunnerInterface $runner = new ShellProcessRunner(),
    ) {}

    /**
     * @param list<string> $arguments arguments after the command name
     *
     * @return array<string, mixed>
     */
    protected function runIndex(string $command, array $arguments = []): array
    {
        $argv = ['php', 'bin/altair', $command, ...$arguments, '--format=json'];
        $result = $this->runner->run($argv, $this->context->projectRoot);

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

    /**
     * @param array<string, mixed> $input
     */
    protected function string(array $input, string $key): ?string
    {
        $value = $input[$key] ?? null;

        return \is_string($value) && $value !== '' ? $value : null;
    }
}
