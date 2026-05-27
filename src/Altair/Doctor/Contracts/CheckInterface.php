<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Doctor\Contracts;

use Altair\Doctor\Result\CheckResult;

/**
 * A single health check.
 *
 * Implementations must be side-effect-free in `run()` (read-only probing);
 * any remediation belongs behind {@see FixableCheckInterface::fix()} and
 * only runs under `--fix`.
 */
interface CheckInterface
{
    /**
     * Stable machine identifier, e.g. `php_version`. Used by `--only` /
     * `--skip` and as the `dependsOn()` key other checks reference.
     */
    public function name(): string;

    /**
     * Names of checks that must pass before this one is meaningful. When a
     * prerequisite errors or is skipped, the runner reports this check as
     * `skipped` rather than running it.
     *
     * @return list<string>
     */
    public function dependsOn(): array;

    public function run(): CheckResult;
}
