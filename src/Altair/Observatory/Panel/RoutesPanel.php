<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Observatory\Panel;

use Altair\Introspection\Inspector\RouteInspector;
use Altair\Observatory\Contracts\PanelInterface;
use Override;

/**
 * Surfaces the framework's registered HTTP routes as a panel.
 *
 * Reuses the introspection {@see RouteInspector}, which walks the
 * RouteCollection without triggering dispatch or middleware resolution, so the
 * panel is safe to render from any context. When no routes are registered the
 * panel degrades to {@see PanelStatus::Unknown} rather than reporting a healthy
 * empty surface.
 */
final readonly class RoutesPanel implements PanelInterface
{
    public function __construct(
        private RouteInspector $inspector,
    ) {}

    #[Override]
    public function id(): string
    {
        return 'routes';
    }

    #[Override]
    public function label(): string
    {
        return 'Routes';
    }

    #[Override]
    public function icon(): string
    {
        return 'map';
    }

    #[Override]
    public function snapshot(): PanelSnapshot
    {
        $table = $this->inspector->inspectAll();

        $items = [];
        foreach ($table->rows as $row) {
            $items[] = [
                'method' => $this->scalarOrNull($row['method'] ?? null),
                'path' => $this->scalarOrNull($row['path'] ?? null),
                'handler' => $this->scalarOrNull($row['action'] ?? null),
            ];
        }

        $count = \count($items);

        if ($count === 0) {
            return new PanelSnapshot(
                PanelStatus::Unknown,
                'no routes registered (unavailable)',
                ['routes' => 0],
            );
        }

        return new PanelSnapshot(
            PanelStatus::Ok,
            \sprintf('%d route%s', $count, $count === 1 ? '' : 's'),
            ['routes' => $count],
            $items,
        );
    }

    private function scalarOrNull(mixed $value): string|int|float|bool|null
    {
        if ($value === null || \is_scalar($value)) {
            return $value;
        }

        return null;
    }
}
