<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Observatory\Panel;

use Altair\Introspection\Inspector\ContainerInspector;
use Altair\Observatory\Contracts\PanelInterface;
use Override;

/**
 * Surfaces the application container's binding inventory as a panel.
 *
 * Reuses the introspection {@see ContainerInspector}, which walks the
 * container's binding collections without triggering instantiation, so the
 * panel is safe to render even when downstream services (database, etc.) are
 * unavailable. A container with no registered bindings degrades to
 * {@see PanelStatus::Unknown}.
 */
final readonly class ContainerPanel implements PanelInterface
{
    public function __construct(
        private ContainerInspector $inspector,
    ) {}

    #[Override]
    public function id(): string
    {
        return 'container';
    }

    #[Override]
    public function label(): string
    {
        return 'Container';
    }

    #[Override]
    public function icon(): string
    {
        return 'cube';
    }

    #[Override]
    public function snapshot(): PanelSnapshot
    {
        $table = $this->inspector->inspectAll();

        $items = [];
        foreach ($table->rows as $row) {
            $items[] = [
                'id' => $this->scalarOrNull($row['id'] ?? null),
                'kind' => $this->scalarOrNull($row['kind'] ?? null),
                'shared' => $this->scalarOrNull($row['shared'] ?? null),
            ];
        }

        $count = \count($items);

        if ($count === 0) {
            return new PanelSnapshot(
                PanelStatus::Unknown,
                'no bindings registered (unavailable)',
                ['bindings' => 0],
            );
        }

        return new PanelSnapshot(
            PanelStatus::Ok,
            \sprintf('%d binding%s', $count, $count === 1 ? '' : 's'),
            ['bindings' => $count],
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
