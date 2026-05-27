<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\TestReporter\Result;

/**
 * One-word verdict for the whole run — the field an agent branches on
 * without parsing the totals.
 */
enum ReportStatus: string
{
    case Pass = 'pass';
    case Fail = 'fail';
    case Error = 'error';
}
