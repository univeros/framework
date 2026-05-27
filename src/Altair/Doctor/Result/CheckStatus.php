<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Doctor\Result;

/**
 * The four outcomes a check can report.
 *
 * `severity()` doubles as the process exit code contribution: a run's exit
 * code is the worst severity observed (0 ok/skipped, 1 warn, 2 error).
 */
enum CheckStatus: string
{
    case Ok = 'ok';
    case Warn = 'warn';
    case Error = 'error';
    case Skipped = 'skipped';

    public function severity(): int
    {
        return match ($this) {
            self::Ok, self::Skipped => 0,
            self::Warn => 1,
            self::Error => 2,
        };
    }
}
