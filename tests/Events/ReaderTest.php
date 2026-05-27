<?php

declare(strict_types=1);

namespace Altair\Tests\Events;

use Altair\Events\Actor;
use Altair\Events\Event;
use Altair\Events\EventKind;
use Altair\Events\EventStatus;
use Altair\Events\Reader;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Reader::class)]
class ReaderTest extends TestCase
{
    public function testTailReturnsNewestFirstUpToN(): void
    {
        $reader = new Reader($this->makeStorage([
            $this->event('01HAAA0000000000000000000A', kind: EventKind::Scaffold),
            $this->event('01HAAA0000000000000000000B', kind: EventKind::Migration),
            $this->event('01HAAA0000000000000000000C', kind: EventKind::Scaffold),
        ]));

        $ids = $this->ids($reader->tail(2));
        $this->assertSame(['01HAAA0000000000000000000C', '01HAAA0000000000000000000B'], $ids);
    }

    public function testTailZeroYieldsNothing(): void
    {
        $reader = new Reader($this->makeStorage([
            $this->event('01HZZZ0000000000000000000A'),
        ]));

        $this->assertSame([], iterator_to_array($reader->tail(0), false));
    }

    public function testSinceFiltersByTimestamp(): void
    {
        $reader = new Reader($this->makeStorage([
            $this->event('01HAAA0000000000000000000A', timestamp: new DateTimeImmutable('2026-05-27T10:00:00Z')),
            $this->event('01HAAA0000000000000000000B', timestamp: new DateTimeImmutable('2026-05-27T11:00:00Z')),
            $this->event('01HAAA0000000000000000000C', timestamp: new DateTimeImmutable('2026-05-27T12:00:00Z')),
        ]));

        $ids = $this->ids($reader->since(new DateTimeImmutable('2026-05-27T10:30:00Z')));

        $this->assertSame(['01HAAA0000000000000000000C', '01HAAA0000000000000000000B'], $ids);
    }

    public function testSinceIdReturnsEverythingAfterTheGivenId(): void
    {
        $reader = new Reader($this->makeStorage([
            $this->event('01HAAA0000000000000000000A'),
            $this->event('01HAAA0000000000000000000B'),
            $this->event('01HAAA0000000000000000000C'),
        ]));

        $ids = $this->ids($reader->sinceId('01HAAA0000000000000000000A'));
        // Newest-first: C (most recent) before B.
        $this->assertSame(['01HAAA0000000000000000000C', '01HAAA0000000000000000000B'], $ids);
    }

    public function testSinceLastSuccessReturnsEventsAfterMostRecentOk(): void
    {
        $reader = new Reader($this->makeStorage([
            $this->event('01HAAA0000000000000000000A', status: EventStatus::Ok),
            $this->event('01HAAA0000000000000000000B', status: EventStatus::Fail, error: 'boom'),
            $this->event('01HAAA0000000000000000000C', status: EventStatus::Partial),
        ]));

        $ids = $this->ids($reader->sinceLastSuccess());

        $this->assertSame(['01HAAA0000000000000000000C', '01HAAA0000000000000000000B'], $ids);
    }

    public function testFilterByKindAndStatus(): void
    {
        $reader = new Reader($this->makeStorage([
            $this->event('01HAAA0000000000000000000A', kind: EventKind::Scaffold, status: EventStatus::Ok),
            $this->event('01HAAA0000000000000000000B', kind: EventKind::Migration, status: EventStatus::Ok),
            $this->event('01HAAA0000000000000000000C', kind: EventKind::Scaffold, status: EventStatus::Fail, error: 'x'),
        ]));

        $ids = $this->ids($reader->filter([EventKind::Scaffold], [EventStatus::Ok]));
        $this->assertSame(['01HAAA0000000000000000000A'], $ids);
    }

    public function testStatsAggregatesByKindAndStatus(): void
    {
        $reader = new Reader($this->makeStorage([
            $this->event('01HAAA0000000000000000000A', kind: EventKind::Scaffold, status: EventStatus::Ok, durationMs: 10),
            $this->event('01HAAA0000000000000000000B', kind: EventKind::Scaffold, status: EventStatus::Ok, durationMs: 20),
            $this->event('01HAAA0000000000000000000C', kind: EventKind::Migration, status: EventStatus::Fail, durationMs: 30, error: 'x'),
        ]));

        $stats = $reader->stats();

        $this->assertSame(3, $stats['total']);
        $this->assertSame(['migration' => 1, 'scaffold' => 2], $stats['by_kind']);
        $this->assertSame(['fail' => 1, 'ok' => 2], $stats['by_status']);
        $this->assertSame(60, $stats['total_duration_ms']);
    }

    public function testFindByIdReturnsNullWhenAbsent(): void
    {
        $reader = new Reader($this->makeStorage([]));
        $this->assertNull($reader->findById('any'));
    }

    /**
     * @param list<Event> $events
     */
    private function makeStorage(array $events): InMemoryStorage
    {
        $storage = new InMemoryStorage();
        foreach ($events as $event) {
            $storage->append($event);
        }

        return $storage;
    }

    /**
     * @param iterable<Event> $events
     * @return list<string>
     */
    private function ids(iterable $events): array
    {
        $out = [];
        foreach ($events as $event) {
            $out[] = $event->id;
        }

        return $out;
    }

    private function event(
        string $id,
        ?DateTimeImmutable $timestamp = null,
        EventKind $kind = EventKind::Scaffold,
        EventStatus $status = EventStatus::Ok,
        int $durationMs = 5,
        ?string $error = null,
    ): Event {
        return new Event(
            id: $id,
            timestamp: $timestamp ?? new DateTimeImmutable('2026-05-27T10:00:00Z'),
            actor: Actor::Cli,
            command: 'bin/altair foo',
            kind: $kind,
            status: $status,
            durationMs: $durationMs,
            error: $error,
        );
    }
}
