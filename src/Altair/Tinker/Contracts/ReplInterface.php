<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Tinker\Contracts;

use Altair\Tinker\Repl\ReplContext;

/**
 * Runs an interactive REPL session and returns its exit code.
 *
 * This is the seam that isolates PsySH: the command depends on the interface,
 * so it stays unit-testable with a fake that never blocks on stdin.
 */
interface ReplInterface
{
    /**
     * Whether the underlying REPL engine is installed and usable.
     */
    public function isAvailable(): bool;

    public function run(ReplContext $context, string $banner): int;
}
