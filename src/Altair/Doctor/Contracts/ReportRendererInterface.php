<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Doctor\Contracts;

use Altair\Doctor\Result\Report;

/**
 * Renders a {@see Report} for a `--format` value (human, json, ...).
 */
interface ReportRendererInterface
{
    public function render(Report $report): string;
}
