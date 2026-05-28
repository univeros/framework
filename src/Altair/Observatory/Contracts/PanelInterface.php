<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Observatory\Contracts;

use Altair\Observatory\Panel\PanelSnapshot;

/**
 * A monitored surface of the application (health, events, queues, routes, ...).
 *
 * A panel is a pure data provider: it reads from an existing framework data
 * source and projects a {@see PanelSnapshot}. Rendering lives in the UI layer,
 * so panels stay testable and reusable by non-HTML consumers.
 */
interface PanelInterface
{
    /**
     * Stable machine identifier (e.g. "runtime", "events", "queues").
     */
    public function id(): string;

    /**
     * Human label for the card/navigation (e.g. "Runtime").
     */
    public function label(): string;

    /**
     * Icon key the UI maps to an SVG (e.g. "server").
     */
    public function icon(): string;

    /**
     * Capture the current state of this surface.
     */
    public function snapshot(): PanelSnapshot;
}
