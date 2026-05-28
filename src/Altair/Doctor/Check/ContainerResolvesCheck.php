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
use Override;
use Psr\Container\ContainerInterface;
use Throwable;

/**
 * Verifies a configured list of critical bindings (PSR-11 ids — e.g.
 * `MiddlewareInterface`, `EntityManagerInterface`) actually resolve. This is
 * the granular companion to {@see ContainerBootsCheck}: boot succeeding
 * doesn't guarantee every contract a host expects is wired.
 *
 * With an empty list the check is `skipped` — hosts opt in by declaring
 * their critical contracts via `DoctorConfiguration`.
 */
final readonly class ContainerResolvesCheck implements CheckInterface
{
    /**
     * @param list<class-string> $criticalBindings
     */
    public function __construct(
        private ContainerInterface $container,
        private array $criticalBindings,
    ) {}

    #[Override]
    public function name(): string
    {
        return 'container_resolves';
    }

    #[Override]
    public function dependsOn(): array
    {
        return ['container_boots'];
    }

    #[Override]
    public function run(): CheckResult
    {
        if ($this->criticalBindings === []) {
            return CheckResult::skipped(
                $this->name(),
                'No critical bindings configured.',
            );
        }

        $failed = [];
        foreach ($this->criticalBindings as $id) {
            try {
                $this->container->get($id);
            } catch (Throwable $throwable) {
                $failed[] = $id . ' (' . $throwable->getMessage() . ')';
            }
        }

        if ($failed === []) {
            return CheckResult::ok(
                $this->name(),
                'All ' . \count($this->criticalBindings) . ' critical bindings resolve.',
            );
        }

        return CheckResult::error(
            $this->name(),
            'Failed to resolve: ' . implode('; ', $failed),
            'Review the Configurations that bind these contracts; ensure each is applied to the Container.',
        );
    }
}
