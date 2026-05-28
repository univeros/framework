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
use Altair\Doctor\Result\CheckResult;
use Closure;
use Override;
use Throwable;

/**
 * Verifies the host application's Container can be constructed from scratch
 * without errors — the most common "agent got stuck" failure mode is a
 * missing Configuration binding that only surfaces at boot.
 *
 * The boot callable is host-supplied (e.g. the same bootstrap `bin/altair`
 * uses). With no callable wired, the check reports `skipped` rather than
 * pass — there's nothing to verify in a library-only context.
 */
final readonly class ContainerBootsCheck implements CheckInterface
{
    /**
     * @param (Closure(): mixed)|null $booter
     */
    public function __construct(
        private ?Closure $booter = null,
    ) {}

    #[Override]
    public function name(): string
    {
        return 'container_boots';
    }

    #[Override]
    public function dependsOn(): array
    {
        return [];
    }

    #[Override]
    public function run(): CheckResult
    {
        if (!$this->booter instanceof Closure) {
            return CheckResult::skipped(
                $this->name(),
                'No application boot callable configured.',
            );
        }

        try {
            ($this->booter)();

            return CheckResult::ok($this->name(), 'Application Container constructed without errors.');
        } catch (Throwable $throwable) {
            return CheckResult::error(
                $this->name(),
                'Boot threw: ' . $throwable->getMessage(),
                'Review the Configurations applied to the Container; the failing one is the first to throw.',
            );
        }
    }
}
