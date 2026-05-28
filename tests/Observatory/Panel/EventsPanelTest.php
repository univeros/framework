<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Tests\Observatory\Panel;

use Altair\Events\Actor;
use Altair\Events\Contracts\EventStorageInterface;
use Altair\Events\Event;
use Altair\Events\EventKind;
use Altair\Events\EventStatus;
use Altair\Events\Reader;
use Altair\Observatory\Panel\EventsPanel;
use Altair\Observatory\Panel\PanelStatus;
use DateTimeImmutable;
use Generator;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EventsPanel::class)]
final class EventsPanelTest extends TestCase
{
    public function testIdentity(): void
    {
        $panel = new EventsPanel(new Reader(new FakeEventStorage([])));

        self::assertSame('events', $panel->id());
        self::assertSame('Activity', $panel->label());
        self::assertSame('rss', $panel->icon());
    }

    public function testSnapshotIsUnknownWhenLogIsEmpty(): void
    {
        $snapshot = (new EventsPanel(new Reader(new FakeEventStorage([]))))->snapshot();

        self::assertSame(PanelStatus::Unknown, $snapshot->status);
        self::assertSame('0 events', $snapshot->headline);
        self::assertSame(0, $snapshot->metrics['total']);
        self::assertSame(0, $snapshot->metrics['ok']);
        self::assertSame(0, $snapshot->metrics['fail']);
        self::assertSame([], $snapshot->items);
    }

    public function testSnapshotIsOkForAllSuccessfulEvents(): void
    {
        $reader = new Reader(new FakeEventStorage([
            $this->event('01HAAA0000000000000000000A', kind: EventKind::Scaffold, durationMs: 10),
            $this->event('01HAAA0000000000000000000B', kind: EventKind::Migration, durationMs: 20),
        ]));

        $snapshot = (new EventsPanel($reader))->snapshot();

        self::assertSame(PanelStatus::Ok, $snapshot->status);
        self::assertSame('2 events', $snapshot->headline);
        self::assertSame(2, $snapshot->metrics['total']);
        self::assertSame(2, $snapshot->metrics['ok']);
        self::assertSame(0, $snapshot->metrics['fail']);
        self::assertSame(30, $snapshot->metrics['total_duration_ms']);

        // Newest-first: B before A.
        self::assertCount(2, $snapshot->items);
        self::assertSame('01HAAA0000000000000000000B', $snapshot->items[0]['id']);
        self::assertSame('migration', $snapshot->items[0]['kind']);
        self::assertSame('ok', $snapshot->items[0]['status']);
        self::assertSame(20, $snapshot->items[0]['duration_ms']);
        self::assertSame('01HAAA0000000000000000000A', $snapshot->items[1]['id']);
    }

    public function testSnapshotIsWarningWhenAnyEventFailed(): void
    {
        $reader = new Reader(new FakeEventStorage([
            $this->event('01HAAA0000000000000000000A', status: EventStatus::Ok, durationMs: 10),
            $this->event('01HAAA0000000000000000000B', status: EventStatus::Fail, durationMs: 5, error: 'boom'),
        ]));

        $snapshot = (new EventsPanel($reader))->snapshot();

        self::assertSame(PanelStatus::Warning, $snapshot->status);
        self::assertSame('2 events', $snapshot->headline);
        self::assertSame(1, $snapshot->metrics['ok']);
        self::assertSame(1, $snapshot->metrics['fail']);
        self::assertSame('fail', $snapshot->items[0]['status']);
    }

    public function testTailLimitsRecentItems(): void
    {
        $reader = new Reader(new FakeEventStorage([
            $this->event('01HAAA0000000000000000000A'),
            $this->event('01HAAA0000000000000000000B'),
            $this->event('01HAAA0000000000000000000C'),
        ]));

        $snapshot = (new EventsPanel($reader, tail: 2))->snapshot();

        self::assertSame(3, $snapshot->metrics['total']);
        self::assertCount(2, $snapshot->items);
        self::assertSame('01HAAA0000000000000000000C', $snapshot->items[0]['id']);
        self::assertSame('01HAAA0000000000000000000B', $snapshot->items[1]['id']);
    }

    private function event(
        string $id,
        EventKind $kind = EventKind::Scaffold,
        EventStatus $status = EventStatus::Ok,
        int $durationMs = 5,
        ?string $error = null,
    ): Event {
        return new Event(
            id: $id,
            timestamp: new DateTimeImmutable('2026-05-27T10:00:00Z'),
            actor: Actor::Cli,
            command: 'bin/altair foo',
            kind: $kind,
            status: $status,
            durationMs: $durationMs,
            error: $error,
        );
    }
}

/**
 * In-memory storage so the panel test never touches the filesystem.
 */
final class FakeEventStorage implements EventStorageInterface
{
    /**
     * @param list<Event> $events oldest -> newest
     */
    public function __construct(
        private array $events = [],
    ) {}

    #[Override]
    public function append(Event $event): void
    {
        $this->events[] = $event;
    }

    #[Override]
    public function readAll(): Generator
    {
        yield from $this->events;
    }

    #[Override]
    public function readReverse(): Generator
    {
        yield from array_reverse($this->events);
    }

    #[Override]
    public function count(): int
    {
        return \count($this->events);
    }
}
