<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\MigrationIntelligence\Contracts;

use Altair\MigrationIntelligence\Plan\PlanSet;

/**
 * Renders a {@see PlanSet} for a `--format` value (human, json).
 */
interface PlanRendererInterface
{
    public function render(PlanSet $plan): string;
}
