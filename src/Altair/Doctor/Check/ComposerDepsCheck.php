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
 * Whether `vendor/` is current with `composer.lock` — `composer install
 * --dry-run` exits non-zero when there's pending work. Fixable: `--fix`
 * runs `composer install`.
 */
final readonly class ComposerDepsCheck implements FixableCheckInterface
{
    public function __construct(
        private ProcessRunnerInterface $runner,
        private string $projectRoot,
    ) {}

    #[Override]
    public function name(): string
    {
        return 'composer_deps';
    }

    #[Override]
    public function dependsOn(): array
    {
        return [];
    }

    #[Override]
    public function run(): CheckResult
    {
        $command = ['composer', 'install', '--dry-run', '--no-interaction', '--no-scripts'];
        if ($this->runner->run($command, $this->projectRoot)->ok()) {
            return CheckResult::ok($this->name(), 'Composer dependencies are current with composer.lock.');
        }

        return CheckResult::warn(
            $this->name(),
            'Composer dependencies are out of date or composer.lock has drifted.',
            'Run `composer install`.',
            AgentAction::runCommand('composer install'),
        );
    }

    #[Override]
    public function fix(): bool
    {
        return $this->runner->run(['composer', 'install', '--no-interaction'], $this->projectRoot)->ok();
    }
}
