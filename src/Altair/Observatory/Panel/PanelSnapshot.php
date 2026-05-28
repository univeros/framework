<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Observatory\Panel;

/**
 * An immutable, render-agnostic read model produced by a panel.
 *
 * The same snapshot feeds the web UI and any JSON/agent consumer, so it carries
 * only plain data: a health status, a one-line headline, a flat metrics map, and
 * an ordered list of detail rows.
 */
final readonly class PanelSnapshot
{
    /**
     * @param array<string, scalar|null> $metrics flat key/value metrics for the card header
     * @param list<array<string, scalar|null>> $items ordered detail rows
     */
    public function __construct(
        public PanelStatus $status,
        public string $headline,
        public array $metrics = [],
        public array $items = [],
    ) {}

    /**
     * @return array{status: string, headline: string, metrics: array<string, scalar|null>, items: list<array<string, scalar|null>>}
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status->value,
            'headline' => $this->headline,
            'metrics' => $this->metrics,
            'items' => $this->items,
        ];
    }
}
