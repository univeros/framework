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
 * If persistence is configured, can the database be reached? The probe is
 * host-supplied (it knows about the project's specific DB binding) — a
 * common shape is `static fn() => $em->getConnection()->isConnected()`.
 *
 * With no probe configured the check is `skipped` — not every project uses
 * a database, and "no probe" must not look like a pass.
 */
final readonly class DatabaseReachableCheck implements CheckInterface
{
    /**
     * @param (Closure(): bool)|null $probe
     */
    public function __construct(
        private ?Closure $probe = null,
    ) {}

    #[Override]
    public function name(): string
    {
        return 'database_reachable';
    }

    #[Override]
    public function dependsOn(): array
    {
        return [];
    }

    #[Override]
    public function run(): CheckResult
    {
        if (!$this->probe instanceof Closure) {
            return CheckResult::skipped(
                $this->name(),
                'No database probe configured.',
            );
        }

        try {
            if (($this->probe)()) {
                return CheckResult::ok($this->name(), 'Database is reachable.');
            }

            return CheckResult::error(
                $this->name(),
                'Database probe returned false.',
                'Check DB credentials, that the database server is running, and that the configured connection name matches the bound DatabaseProvider.',
            );
        } catch (Throwable $throwable) {
            return CheckResult::error(
                $this->name(),
                'Database probe threw: ' . $throwable->getMessage(),
                'Check DB connection settings and that the persistence layer is wired.',
            );
        }
    }
}
