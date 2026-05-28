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
use Altair\Events\EventKind;
use Altair\Events\EventStatus;
use Altair\Mcp\Attribute\McpTool;
use Altair\Mcp\Contracts\McpToolInterface;
use Altair\Mcp\Exception\GuardrailException;
use Altair\Mcp\Guard\ServerMode;
use Altair\Mcp\Support\EventLog;
use Altair\Mcp\Support\Output;
use Altair\Mcp\Support\ProjectContext;
use Override;

#[McpTool(
    name: 'framework__db_migrate',
    description: 'Apply pending database migrations. Writes require the server started with --allow-writes.',
    inputSchema: __DIR__ . '/../../Schema/db-migrate-input.json',
    outputSchema: __DIR__ . '/../../Schema/object-output.json',
)]
final readonly class DbMigrateTool implements McpToolInterface
{
    public function __construct(
        private ProjectContext $context,
        private ServerMode $mode,
        private EventLog $events,
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
        $dryRun = ($input['dry_run'] ?? false) === true;

        if (!$dryRun && !$this->mode->allowsDbWrites()) {
            throw new GuardrailException(
                'Applying migrations requires starting the server with --allow-writes (and not --readonly). '
                . 'Use dry_run to preview pending migrations.',
            );
        }

        $command = ['php', 'bin/altair', 'db:migrate'];
        if ($dryRun) {
            $command[] = '--dry-run';
        }

        $result = $this->runner->run($command, $this->context->projectRoot);

        if (!$dryRun) {
            $this->events->record(
                EventKind::Migration,
                $result->ok() ? EventStatus::Ok : EventStatus::Fail,
                'mcp framework__db_migrate',
                0,
                $result->ok() ? null : 'migration command failed',
            );
        }

        return [
            'passed' => $result->ok(),
            'exit_code' => $result->exitCode,
            'dry_run' => $dryRun,
            'output' => Output::tail($result->stdout . $result->stderr),
        ];
    }
}
