<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Mcp\Tool\Database;

use Altair\Doctor\Contracts\ProcessRunnerInterface;
use Altair\Doctor\Process\ShellProcessRunner;
use Altair\Mcp\Attribute\McpTool;
use Altair\Mcp\Contracts\McpToolInterface;
use Altair\Mcp\Support\Output;
use Altair\Mcp\Support\ProjectContext;
use Override;

/**
 * Read-only wrapper over `bin/altair db:migration-plan --format=json`.
 *
 * Computes a safe migration plan from a spec/entity diff (or spec-vs-spec) and
 * returns the structured plan — operations, per-dialect preview SQL, and the
 * read-only safety report. Planning never writes files or mutates data, so it
 * needs no write guard; the agent reviews the plan before scaffolding it.
 */
#[McpTool(
    name: 'framework__plan_migration',
    description: 'Propose a safe database migration from a spec/entity diff, with read-only safety checks. '
        . 'Provide a spec path, from_entity, or from_spec + to_spec.',
    inputSchema: __DIR__ . '/../../Schema/plan-migration-input.json',
    outputSchema: __DIR__ . '/../../Schema/object-output.json',
)]
final readonly class PlanMigrationTool implements McpToolInterface
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
        $command = ['php', 'bin/altair', 'db:migration-plan', '--format=json'];

        $spec = $this->string($input, 'spec');
        if ($spec !== null) {
            $command[] = $spec;
        }

        foreach (['from_entity' => '--from-entity', 'from_spec' => '--from-spec', 'to_spec' => '--to-spec', 'driver' => '--driver', 'rename' => '--rename'] as $key => $flag) {
            $value = $this->string($input, $key);
            if ($value !== null) {
                $command[] = $flag . '=' . $value;
            }
        }

        if (($input['skip_safety'] ?? false) === true) {
            $command[] = '--skip-safety';
        }

        $result = $this->runner->run($command, $this->context->projectRoot);

        $plan = json_decode($result->stdout, true);
        if (\is_array($plan)) {
            return [
                'ok' => $result->exitCode === 0,
                'exit_code' => $result->exitCode,
                'plan' => $plan,
            ];
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
    private function string(array $input, string $key): ?string
    {
        $value = $input[$key] ?? null;

        return \is_string($value) && $value !== '' ? $value : null;
    }
}
