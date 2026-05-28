<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Doctor\Check;

use Altair\Doctor\Contracts\CheckInterface;
use Altair\Doctor\Contracts\ProcessRunnerInterface;
use Altair\Doctor\Result\AgentAction;
use Altair\Doctor\Result\CheckResult;
use Override;

/**
 * Hand-edits to generated files surface here via `bin/altair spec:lint`,
 * which compares the emitted artifacts against their YAML specs and exits
 * non-zero on drift. Fix path: regenerate from the spec (`spec:scaffold`).
 */
final readonly class SpecDriftCheck implements CheckInterface
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
        return 'spec_drift';
    }

    #[Override]
    public function dependsOn(): array
    {
        return [];
    }

    #[Override]
    public function run(): CheckResult
    {
        $command = [...$this->altairBin, 'spec:lint'];
        if ($this->runner->run($command, $this->projectRoot)->ok()) {
            return CheckResult::ok($this->name(), 'Scaffolded files match their YAML specs.');
        }

        return CheckResult::warn(
            $this->name(),
            'Scaffolded files have drifted from their YAML specs (hand-edits or stale generation).',
            'Re-run `bin/altair spec:scaffold` to regenerate, or revert the hand-edits to the spec source.',
            AgentAction::runCommand('bin/altair spec:scaffold'),
        );
    }
}
