<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Observatory\Panel;

use Altair\Introspection\Inspector\ConfigInspector;
use Altair\Observatory\Contracts\PanelInterface;
use Override;

/**
 * Surfaces the merged environment + container-parameter configuration as a
 * panel.
 *
 * Reuses the introspection {@see ConfigInspector} and always calls
 * `dump()` with masking enabled, so secret-looking keys (PASSWORD, SECRET,
 * TOKEN, KEY, ...) are redacted to `***` before they reach the snapshot. The
 * panel never requests the raw dump, so it cannot leak secret values. An empty
 * configuration degrades to {@see PanelStatus::Unknown}.
 */
final readonly class ConfigPanel implements PanelInterface
{
    public function __construct(
        private ConfigInspector $inspector,
    ) {}

    #[Override]
    public function id(): string
    {
        return 'config';
    }

    #[Override]
    public function label(): string
    {
        return 'Config';
    }

    #[Override]
    public function icon(): string
    {
        return 'adjustments';
    }

    #[Override]
    public function snapshot(): PanelSnapshot
    {
        // Masking is always on: the panel never surfaces raw secret values.
        $table = $this->inspector->dump(maskSecrets: true);

        $items = [];
        foreach ($table->rows as $row) {
            $items[] = [
                'key' => $this->scalarOrNull($row['key'] ?? null),
                'value' => $this->scalarOrNull($row['value'] ?? null),
            ];
        }

        $count = \count($items);

        if ($count === 0) {
            return new PanelSnapshot(
                PanelStatus::Unknown,
                'no configuration available (unavailable)',
                ['keys' => 0],
            );
        }

        return new PanelSnapshot(
            PanelStatus::Ok,
            \sprintf('%d key%s', $count, $count === 1 ? '' : 's'),
            ['keys' => $count],
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
