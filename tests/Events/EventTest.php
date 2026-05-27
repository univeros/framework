<?php

declare(strict_types=1);

namespace Altair\Tests\Events;

use Altair\Events\Actor;
use Altair\Events\Changes;
use Altair\Events\Event;
use Altair\Events\EventKind;
use Altair\Events\EventStatus;
use Altair\Events\Exception\InvalidArgumentException;
use DateTimeImmutable;
use DateTimeInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Event::class)]
#[CoversClass(Changes::class)]
#[CoversClass(Actor::class)]
#[CoversClass(EventStatus::class)]
#[CoversClass(EventKind::class)]
class EventTest extends TestCase
{
    public function testCreateStampsUlidAndCurrentTimestamp(): void
    {
        $event = Event::create(
            actor: Actor::Cli,
            command: 'bin/altair spec:scaffold api/users/create.yaml',
            kind: EventKind::Scaffold,
            status: EventStatus::Ok,
            durationMs: 12,
        );

        $this->assertNotSame('', $event->id);
        $this->assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{26}$/i', $event->id);
        $this->assertSame(EventStatus::Ok, $event->status);
        $this->assertSame(12, $event->durationMs);
    }

    public function testEmptyCommandIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Event(
            id: '01H',
            timestamp: new DateTimeImmutable('2026-05-27T00:00:00Z'),
            actor: Actor::Cli,
            command: '',
            kind: EventKind::Scaffold,
            status: EventStatus::Ok,
            durationMs: 0,
        );
    }

    public function testNegativeDurationIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Event(
            id: '01H',
            timestamp: new DateTimeImmutable('2026-05-27T00:00:00Z'),
            actor: Actor::Cli,
            command: 'bin/altair foo',
            kind: EventKind::Scaffold,
            status: EventStatus::Ok,
            durationMs: -1,
        );
    }

    public function testFailedEventRequiresError(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Event(
            id: '01H',
            timestamp: new DateTimeImmutable('2026-05-27T00:00:00Z'),
            actor: Actor::Cli,
            command: 'bin/altair foo',
            kind: EventKind::Scaffold,
            status: EventStatus::Fail,
            durationMs: 5,
            error: null,
        );
    }

    public function testRoundTripsThroughJson(): void
    {
        $original = Event::create(
            actor: Actor::Cli,
            command: 'bin/altair spec:scaffold api/users/create.yaml',
            kind: EventKind::Scaffold,
            status: EventStatus::Ok,
            durationMs: 847,
            user: 'tonydspaniard',
            changes: new Changes(['created' => ['src/App/Http/Actions/CreateUserAction.php']]),
            extra: ['spec_sha' => '4f3a8b'],
        );

        $line = $original->toJsonLine();
        $this->assertJson($line);
        $decoded = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
        $rebuilt = Event::fromArray($decoded);

        $this->assertSame($original->id, $rebuilt->id);
        $this->assertSame($original->command, $rebuilt->command);
        $this->assertSame($original->kind, $rebuilt->kind);
        $this->assertSame($original->status, $rebuilt->status);
        $this->assertSame($original->durationMs, $rebuilt->durationMs);
        $this->assertSame($original->user, $rebuilt->user);
        $this->assertSame(
            $original->timestamp->format(DateTimeInterface::RFC3339_EXTENDED),
            $rebuilt->timestamp->format(DateTimeInterface::RFC3339_EXTENDED),
        );
        $this->assertNotNull($rebuilt->changes);
        $this->assertSame(['created' => ['src/App/Http/Actions/CreateUserAction.php']], $rebuilt->changes->buckets);
        $this->assertSame(['spec_sha' => '4f3a8b'], $rebuilt->extra);
    }

    public function testFromArrayRejectsMissingFields(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Event::fromArray(['id' => '01H']);
    }

    public function testEventKindFromStringRejectsUnknown(): void
    {
        $this->expectException(InvalidArgumentException::class);
        EventKind::fromString('not-a-kind');
    }
}
