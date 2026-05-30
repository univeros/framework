<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Tests\Examples;

use Altair\Events\Actor;
use Altair\Events\Event;
use Altair\Events\EventKind;
use Altair\Events\EventStatus;
use Altair\Events\Reader;
use Altair\Events\Recorder;
use Altair\Events\Scrubber;
use Altair\Events\Storage\JsonlStorage;
use PHPUnit\Framework\TestCase;

/**
 * Runs the snippet from .altair/examples/events/recorder-roundtrip.md
 * end-to-end. Asserts Recorder persists an event and Reader yields it back
 * intact (a small, real on-disk JSONL store under sys_get_temp_dir).
 */
final class EventsRecorderRoundtripTest extends TestCase
{
    private string $logPath;

    protected function setUp(): void
    {
        $this->logPath = sys_get_temp_dir() . '/altair-example-events-' . bin2hex(random_bytes(4)) . '.jsonl';
    }

    protected function tearDown(): void
    {
        @unlink($this->logPath);
    }

    public function testRecordedEventIsReadableViaReader(): void
    {
        $storage = new JsonlStorage($this->logPath);
        $recorder = new Recorder($storage, new Scrubber());
        $reader = new Reader($storage);

        $recorder->record(Event::create(
            actor: Actor::Cli,
            command: 'bin/altair example:command',
            kind: EventKind::ManualEdit,
            status: EventStatus::Ok,
            durationMs: 42,
        ));

        $events = iterator_to_array($reader->all());

        self::assertCount(1, $events);
        $event = $events[0];
        self::assertSame('bin/altair example:command', $event->command);
        self::assertSame(EventStatus::Ok, $event->status);
        self::assertSame(42, $event->durationMs);
        self::assertSame(EventKind::ManualEdit, $event->kind);
    }

    public function testScrubberRedactsSecretFlags(): void
    {
        $storage = new JsonlStorage($this->logPath);
        $recorder = new Recorder($storage, new Scrubber());
        $reader = new Reader($storage);

        $recorder->record(Event::create(
            actor: Actor::Cli,
            command: 'bin/altair db:migrate --password=hunter2',
            kind: EventKind::Migration,
            status: EventStatus::Ok,
            durationMs: 10,
        ));

        $event = iterator_to_array($reader->all())[0];

        self::assertStringContainsString('--password=***', $event->command);
        self::assertStringNotContainsString('hunter2', $event->command);
    }
}
