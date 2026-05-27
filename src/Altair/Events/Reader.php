<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Events;

use Altair\Events\Contracts\EventStorageInterface;
use DateTimeInterface;
use Generator;

/**
 * Query layer over a backing {@see EventStorageInterface}.
 *
 * The storage is content-blind: it just yields events. The Reader applies
 * filters (kind, status, time, id) and projections (since-checkpoint, last
 * N, stats) — the things every CLI command and MCP tool needs.
 */
final readonly class Reader
{
    public function __construct(
        private EventStorageInterface $storage,
    ) {}

    /**
     * @return Generator<int, Event>
     */
    public function all(): Generator
    {
        yield from $this->storage->readAll();
    }

    /**
     * The last $n events in newest-first order.
     *
     * @return Generator<int, Event>
     */
    public function tail(int $n): Generator
    {
        if ($n <= 0) {
            return;
        }

        $count = 0;
        foreach ($this->storage->readReverse() as $event) {
            yield $event;
            if (++$count >= $n) {
                return;
            }
        }
    }

    /**
     * Newest-first events strictly after the given timestamp.
     *
     * @return Generator<int, Event>
     */
    public function since(DateTimeInterface $threshold): Generator
    {
        foreach ($this->storage->readReverse() as $event) {
            if ($event->timestamp <= $threshold) {
                return;
            }

            yield $event;
        }
    }

    /**
     * Newest-first events strictly after the event with the given id.
     *
     * If the id is not found, the entire log is yielded.
     *
     * @return Generator<int, Event>
     */
    public function sinceId(string $eventId): Generator
    {
        $buffer = [];
        foreach ($this->storage->readReverse() as $event) {
            if ($event->id === $eventId) {
                break;
            }

            $buffer[] = $event;
        }

        yield from $buffer;
    }

    public function findById(string $eventId): ?Event
    {
        foreach ($this->storage->readReverse() as $event) {
            if ($event->id === $eventId) {
                return $event;
            }
        }

        return null;
    }

    /**
     * Newest-first events back to (and not including) the most recent
     * event whose status is {@see EventStatus::Ok}.
     *
     * @return Generator<int, Event>
     */
    public function sinceLastSuccess(): Generator
    {
        foreach ($this->storage->readReverse() as $event) {
            if ($event->status === EventStatus::Ok) {
                return;
            }

            yield $event;
        }
    }

    /**
     * Apply a kind/status filter to the full log, newest-first.
     *
     * @param list<EventKind>   $kinds    Empty = all kinds
     * @param list<EventStatus> $statuses Empty = all statuses
     *
     * @return Generator<int, Event>
     */
    public function filter(array $kinds = [], array $statuses = []): Generator
    {
        $kindValues = array_map(static fn(EventKind $k) => $k->value, $kinds);
        $statusValues = array_map(static fn(EventStatus $s) => $s->value, $statuses);

        foreach ($this->storage->readReverse() as $event) {
            if ($kindValues !== [] && !\in_array($event->kind->value, $kindValues, true)) {
                continue;
            }

            if ($statusValues !== [] && !\in_array($event->status->value, $statusValues, true)) {
                continue;
            }

            yield $event;
        }
    }

    /**
     * @return array{
     *     total: int,
     *     by_kind: array<string, int>,
     *     by_status: array<string, int>,
     *     total_duration_ms: int,
     *     first_at: ?string,
     *     last_at: ?string
     * }
     */
    public function stats(): array
    {
        $total = 0;
        $byKind = [];
        $byStatus = [];
        $duration = 0;
        $first = null;
        $last = null;

        foreach ($this->storage->readAll() as $event) {
            $total++;
            $byKind[$event->kind->value] = ($byKind[$event->kind->value] ?? 0) + 1;
            $byStatus[$event->status->value] = ($byStatus[$event->status->value] ?? 0) + 1;
            $duration += $event->durationMs;
            $iso = $event->timestamp->format(DateTimeInterface::RFC3339_EXTENDED);
            $first ??= $iso;
            $last = $iso;
        }

        ksort($byKind);
        ksort($byStatus);

        return [
            'total' => $total,
            'by_kind' => $byKind,
            'by_status' => $byStatus,
            'total_duration_ms' => $duration,
            'first_at' => $first,
            'last_at' => $last,
        ];
    }
}
