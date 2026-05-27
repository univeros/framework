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
 * Code-style drift via `composer cs`. Fixable: `--fix` runs `composer cs:fix`.
 */
final readonly class CsCleanCheck implements FixableCheckInterface
{
    public function __construct(
        private ProcessRunnerInterface $runner,
        private string $projectRoot,
    ) {}

    #[Override]
    public function name(): string
    {
        return 'cs_clean';
    }

    #[Override]
    public function dependsOn(): array
    {
        return [];
    }

    #[Override]
    public function run(): CheckResult
    {
        if ($this->runner->run(['composer', 'cs'], $this->projectRoot)->ok()) {
            return CheckResult::ok($this->name(), 'Code style is clean.');
        }

        return CheckResult::warn(
            $this->name(),
            'Code style violations found.',
            'Run `composer cs:fix`.',
            AgentAction::runCommand('composer cs:fix'),
        );
    }

    #[Override]
    public function fix(): bool
    {
        return $this->runner->run(['composer', 'cs:fix'], $this->projectRoot)->ok();
    }
}
