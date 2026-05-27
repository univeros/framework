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
use Altair\Doctor\Result\AgentAction;
use Altair\Doctor\Result\CheckResult;
use Override;

use const PHP_VERSION;

/**
 * Verifies the active PHP runtime satisfies the project's `composer.json`
 * floor. The most common "agent got stuck" cause is the wrong PHP on PATH.
 */
final readonly class PhpVersionCheck implements CheckInterface
{
    public function __construct(
        private string $floor,
        private string $current = PHP_VERSION,
    ) {}

    #[Override]
    public function name(): string
    {
        return 'php_version';
    }

    #[Override]
    public function dependsOn(): array
    {
        return [];
    }

    #[Override]
    public function run(): CheckResult
    {
        if (version_compare($this->current, $this->floor, '>=')) {
            return CheckResult::ok(
                $this->name(),
                \sprintf('PHP %s satisfies >=%s', $this->current, $this->floor),
            );
        }

        return CheckResult::error(
            $this->name(),
            \sprintf('PHP %s is below the required >=%s', $this->current, $this->floor),
            \sprintf('Install PHP >= %s and put it first on PATH.', $this->floor),
            AgentAction::installDep('php@' . $this->floor),
        );
    }
}
