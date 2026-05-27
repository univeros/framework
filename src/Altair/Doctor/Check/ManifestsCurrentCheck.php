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
 * Whether `.agent/packages/*.md` still matches what the generator would
 * produce now (via `bin/altair manifest:diff`, which exits non-zero on
 * drift). Fixable: `--fix` regenerates with `manifest:generate`.
 */
final readonly class ManifestsCurrentCheck implements FixableCheckInterface
{
    /**
     * @param list<string> $altairBin argv prefix for the CLI, e.g. ['php', 'bin/altair']
     */
    public function __construct(
        private ProcessRunnerInterface $runner,
        private string $projectRoot,
        private array $altairBin = ['php', 'bin/altair'],
    ) {}

    #[Override]
    public function name(): string
    {
        return 'manifests_current';
    }

    #[Override]
    public function dependsOn(): array
    {
        return [];
    }

    #[Override]
    public function run(): CheckResult
    {
        $command = [...$this->altairBin, 'manifest:diff', '--format=json'];
        if ($this->runner->run($command, $this->projectRoot)->ok()) {
            return CheckResult::ok($this->name(), 'Agent manifests are current.');
        }

        return CheckResult::warn(
            $this->name(),
            'Agent manifests are stale — .agent/ does not match the current source.',
            'Run `bin/altair manifest:generate`.',
            AgentAction::runCommand('bin/altair manifest:generate'),
        );
    }

    #[Override]
    public function fix(): bool
    {
        $command = [...$this->altairBin, 'manifest:generate'];

        return $this->runner->run($command, $this->projectRoot)->ok();
    }
}
