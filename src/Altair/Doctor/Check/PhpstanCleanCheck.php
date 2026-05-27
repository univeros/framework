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
use Altair\Doctor\Result\CheckResult;
use Override;

/**
 * Static-analysis regressions via `composer stan`. Not auto-fixable — type
 * errors need a human/agent edit at the root cause — so it reports an error
 * with guidance rather than an `agent_action`.
 */
final readonly class PhpstanCleanCheck implements CheckInterface
{
    public function __construct(
        private ProcessRunnerInterface $runner,
        private string $projectRoot,
    ) {}

    #[Override]
    public function name(): string
    {
        return 'phpstan_clean';
    }

    #[Override]
    public function dependsOn(): array
    {
        return [];
    }

    #[Override]
    public function run(): CheckResult
    {
        if ($this->runner->run(['composer', 'stan'], $this->projectRoot)->ok()) {
            return CheckResult::ok($this->name(), 'PHPStan reports no errors.');
        }

        return CheckResult::error(
            $this->name(),
            'PHPStan reported type errors.',
            'Run `composer stan` and fix the reported errors at the root cause (or add a justified baseline entry).',
        );
    }
}
