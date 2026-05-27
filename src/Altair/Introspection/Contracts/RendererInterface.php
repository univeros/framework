<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Introspection\Contracts;

use Altair\Introspection\Result\InspectionTable;

/**
 * Renders an {@see InspectionTable} to a single string ready for stdout.
 *
 * Pluggable per output format (`human` / `json`); CLI commands resolve
 * the right one from {@see RendererRegistry}.
 */
interface RendererInterface
{
    public function render(InspectionTable $table): string;
}
