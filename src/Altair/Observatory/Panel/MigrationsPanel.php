<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Observatory\Panel;

use Altair\Observatory\Contracts\MigrationStatusReaderInterface;
use Altair\Observatory\Contracts\PanelInterface;
use Override;

/**
 * Surfaces Cycle ORM migration state as a panel: how many migrations have been
 * applied versus pending, with the pending ones listed first.
 *
 * Reads through {@see MigrationStatusReaderInterface} only, never touching Cycle
 * or a live connection directly. When the reader cannot determine status (no
 * database, unconfigured migrator) it yields `null` and the panel degrades to
 * {@see PanelStatus::Unknown} with an "unavailable" headline rather than failing
 * the whole dashboard.
 */
final readonly class MigrationsPanel implements PanelInterface
{
    private const int RECENT_LIMIT = 25;

    public function __construct(
        private MigrationStatusReaderInterface $reader,
    ) {}

    #[Override]
    public function id(): string
    {
        return 'migrations';
    }

    #[Override]
    public function label(): string
    {
        return 'Migrations';
    }

    #[Override]
    public function icon(): string
    {
        return 'circle-stack';
    }

    #[Override]
    public function snapshot(): PanelSnapshot
    {
        $migrations = $this->reader->read();

        if ($migrations === null) {
            return new PanelSnapshot(
                PanelStatus::Unknown,
                'unavailable',
                [
                    'applied' => null,
                    'pending' => null,
                ],
            );
        }

        $applied = 0;
        $pending = [];
        foreach ($migrations as $migration) {
            if ($migration->applied) {
                $applied++;

                continue;
            }

            $pending[] = $migration;
        }

        $pendingCount = \count($pending);

        return new PanelSnapshot(
            $pendingCount > 0 ? PanelStatus::Warning : PanelStatus::Ok,
            \sprintf('%d pending', $pendingCount),
            [
                'applied' => $applied,
                'pending' => $pendingCount,
            ],
            $this->items($migrations, $pending),
        );
    }

    /**
     * Pending migrations first (the actionable rows); if none are pending the
     * most recent applied migrations are shown instead, so the panel is never
     * empty when migrations exist.
     *
     * @param list<MigrationStatus> $migrations
     * @param list<MigrationStatus> $pending
     *
     * @return list<array<string, scalar|null>>
     */
    private function items(array $migrations, array $pending): array
    {
        $rows = $pending !== [] ? $pending : \array_slice($migrations, -self::RECENT_LIMIT);

        $items = [];
        foreach ($rows as $migration) {
            $items[] = [
                'name' => $migration->name,
                'applied' => $migration->applied,
            ];
        }

        return $items;
    }
}
