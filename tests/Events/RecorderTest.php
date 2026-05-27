<?php

declare(strict_types=1);

namespace Altair\Tests\Events;

use Altair\Events\Actor;
use Altair\Events\Contracts\EventStorageInterface;
use Altair\Events\Event;
use Altair\Events\EventKind;
use Altair\Events\EventStatus;
use Altair\Events\Exception\StorageException;
use Altair\Events\NullRecorder;
use Altair\Events\Recorder;
use Altair\Events\Scrubber;
use Generator;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

#[CoversClass(Recorder::class)]
#[CoversClass(NullRecorder::class)]
class RecorderTest extends TestCase
{
    public function testRecordScrubsSecretsBeforePersisting(): void
    {
        $storage = new InMemoryStorage();
        $recorder = new Recorder($storage, new Scrubber());

        $recorder->record(Event::create(
            actor: Actor::Cli,
            command: 'bin/altair db:migrate --password=hunter2',
            kind: EventKind::Migration,
            status: EventStatus::Ok,
            durationMs: 50,
        ));

        $persisted = $storage->events[0];
        $this->assertStringContainsString('--password=***', $persisted->command);
        $this->assertStringNotContainsString('hunter2', $persisted->command);
    }

    public function testStorageFailuresAreSwallowedAndLogged(): void
    {
        $logger = new RecordingLogger();
        $recorder = new Recorder(new FailingStorage(), new Scrubber(), $logger);

        // Must not throw — recording is best-effort.
        $recorder->record(Event::create(
            actor: Actor::Cli,
            command: 'bin/altair foo',
            kind: EventKind::Scaffold,
            status: EventStatus::Ok,
            durationMs: 1,
        ));

        $this->assertCount(1, $logger->records);
        $this->assertSame('warning', $logger->records[0]['level']);
    }

    public function testNullRecorderIsNoOp(): void
    {
        $recorder = new NullRecorder();
        $recorder->record(Event::create(
            actor: Actor::Cli,
            command: 'bin/altair foo',
            kind: EventKind::Scaffold,
            status: EventStatus::Ok,
            durationMs: 1,
        ));

        $this->assertTrue(true); // reached without throwing
    }
}

final class InMemoryStorage implements EventStorageInterface
{
    /** @var list<Event> */
    public array $events = [];

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

final class FailingStorage implements EventStorageInterface
{
    #[Override]
    public function append(Event $event): void
    {
        throw new StorageException('disk full');
    }

    #[Override]
    public function readAll(): Generator
    {
        yield from [];
    }

    #[Override]
    public function readReverse(): Generator
    {
        yield from [];
    }

    #[Override]
    public function count(): int
    {
        return 0;
    }
}

final class RecordingLogger extends AbstractLogger
{
    /** @var list<array{level: mixed, message: string|\Stringable, context: array}> */
    public array $records = [];

    #[Override]
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->records[] = ['level' => $level, 'message' => $message, 'context' => $context];
    }
}
