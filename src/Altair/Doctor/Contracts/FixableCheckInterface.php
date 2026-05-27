<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Doctor\Contracts;

/**
 * A check that can attempt its own safe, non-destructive remediation.
 *
 * `fix()` only runs under `bin/altair doctor --fix`, and must never perform
 * destructive operations (data deletes, downgrades, force-pushes).
 */
interface FixableCheckInterface extends CheckInterface
{
    /**
     * Attempt the fix. Returns true if it was applied (the runner then
     * re-runs the check to confirm the new status).
     */
    public function fix(): bool;
}
