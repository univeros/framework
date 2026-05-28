<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Suggest\Contracts;

use Altair\Suggest\Result\SuggestionReport;

/**
 * Renders a {@see SuggestionReport} for a `--format` value (human, json, ...).
 */
interface SuggestionRendererInterface
{
    public function render(SuggestionReport $report): string;
}
