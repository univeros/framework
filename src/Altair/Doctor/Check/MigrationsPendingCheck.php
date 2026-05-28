<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Doctor\Check;

use Altair\Doctor\Contracts\FixableCheckInterface;
use Altair\Doctor\Contracts\ProcessRunnerInterface;
use Altair\Doctor\Result\AgentAction;
use Altair\Doctor\Result\CheckResult;
use Override;

/**
 * Reports drift between the migrations directory and what's been applied to
 * the database. `bin/altair db:migrate:status` exits non-zero when there's
 * pending work. Fixable: `--fix` runs `db:migrate`.
 */
final readonly class MigrationsPendingCheck implements FixableCheckInterface
{
    /**
     * @param list<string> $altairBin
     */
    public function __construct(
        private ProcessRunnerInterface $runner,
        private string $projectRoot,
        private array $altairBin = ['php', 'bin/altair'],
    ) {}

    #[Override]
    public function name(): string
    {
        return 'migrations_pending';
    }

    #[Override]
    public function dependsOn(): array
    {
        return ['database_reachable'];
    }

    #[Override]
    public function run(): CheckResult
    {
        $command = [...$this->altairBin, 'db:migrate:status'];
        if ($this->runner->run($command, $this->projectRoot)->ok()) {
            return CheckResult::ok($this->name(), 'No pending migrations.');
        }

        return CheckResult::warn(
            $this->name(),
            'There are unapplied migrations.',
            'Run `bin/altair db:migrate`.',
            AgentAction::runCommand('bin/altair db:migrate'),
        );
    }

    #[Override]
    public function fix(): bool
    {
        return $this->runner->run([...$this->altairBin, 'db:migrate'], $this->projectRoot)->ok();
    }
}
