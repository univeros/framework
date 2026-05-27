<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Doctor\Contracts;

use Altair\Doctor\Process\ProcessResult;

/**
 * Abstracts sub-process execution so process-backed checks (running
 * `composer`, `bin/altair`, `git`, ...) stay unit-testable with a fake
 * runner instead of shelling out for real.
 */
interface ProcessRunnerInterface
{
    /**
     * @param list<string> $command argv form (no shell), e.g. ['composer', 'cs']
     */
    public function run(array $command, ?string $cwd = null): ProcessResult;
}
