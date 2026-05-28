<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Observatory\Panel;

use Altair\Events\Reader;
use Altair\Observatory\Contracts\PanelInterface;
use DateTimeInterface;
use Override;

/**
 * Surfaces the append-only mutation event log (`.altair/events.jsonl`) as a
 * panel: total volume, a per-status breakdown, and the most recent activity.
 *
 * Reads through {@see Reader} only, so it stays content-blind about storage and
 * is trivially testable against an in-memory backing store.
 */
final readonly class EventsPanel implements PanelInterface
{
    public function __construct(
        private Reader $reader,
        private int $tail = 25,
    ) {}

    #[Override]
    public function id(): string
    {
        return 'events';
    }

    #[Override]
    public function label(): string
    {
        return 'Activity';
    }

    #[Override]
    public function icon(): string
    {
        return 'rss';
    }

    #[Override]
    public function snapshot(): PanelSnapshot
    {
        $stats = $this->reader->stats();

        $total = $stats['total'];
        $ok = $stats['by_status']['ok'] ?? 0;
        $fail = $stats['by_status']['fail'] ?? 0;

        $status = match (true) {
            $total === 0 => PanelStatus::Unknown,
            $fail > 0 => PanelStatus::Warning,
            default => PanelStatus::Ok,
        };

        return new PanelSnapshot(
            $status,
            \sprintf('%s %s', number_format($total), $total === 1 ? 'event' : 'events'),
            [
                'total' => $total,
                'ok' => $ok,
                'fail' => $fail,
                'total_duration_ms' => $stats['total_duration_ms'],
            ],
            $this->recentItems(),
        );
    }

    /**
     * Recent events flattened to scalar-only rows, newest first.
     *
     * @return list<array<string, scalar|null>>
     */
    private function recentItems(): array
    {
        $items = [];
        foreach ($this->reader->tail($this->tail) as $event) {
            $items[] = [
                'id' => $event->id,
                'kind' => $event->kind->value,
                'status' => $event->status->value,
                'timestamp' => $event->timestamp->format(DateTimeInterface::RFC3339_EXTENDED),
                'duration_ms' => $event->durationMs,
            ];
        }

        return $items;
    }
}
