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
 * PHPUnit exits 0. Slow — declare it skippable in CI/local with
 * `--skip=tests_passing` when running doctor as a fast feedback loop. Depends
 * on `composer_deps` (no point running the suite if vendor is stale).
 */
final readonly class TestsPassingCheck implements CheckInterface
{
    public function __construct(
        private ProcessRunnerInterface $runner,
        private string $projectRoot,
    ) {}

    #[Override]
    public function name(): string
    {
        return 'tests_passing';
    }

    #[Override]
    public function dependsOn(): array
    {
        return ['composer_deps'];
    }

    #[Override]
    public function run(): CheckResult
    {
        if ($this->runner->run(['vendor/bin/phpunit', '--no-progress'], $this->projectRoot)->ok()) {
            return CheckResult::ok($this->name(), 'PHPUnit suite passes.');
        }

        return CheckResult::error(
            $this->name(),
            'PHPUnit reported failures.',
            'Run `vendor/bin/phpunit` to see the failing tests and fix the regression at root cause.',
        );
    }
}
