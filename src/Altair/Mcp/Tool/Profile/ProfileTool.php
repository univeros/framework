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
use Altair\Mcp\Contracts\McpToolInterface;
use Altair\Mcp\Support\Output;
use Altair\Mcp\Support\ProjectContext;

/**
 * Shared base for the read-some profiling tools.
 *
 * Each wraps a `bin/altair profile:* --format=json` invocation in the host
 * project. The decoded CLI payload is returned as-is with an added `ok` flag,
 * matching the conventions of the Index and Eval tool families.
 */
abstract readonly class ProfileTool implements McpToolInterface
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
    protected function runProfile(string $command, array $arguments = []): array
    {
        $argv = ['php', 'bin/altair', $command, '--format=json', ...$arguments];
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
