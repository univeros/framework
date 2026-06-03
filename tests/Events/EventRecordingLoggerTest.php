<?php

declare(strict_types=1);

namespace Altair\Tests\Events;

use Altair\Events\Contracts\RecorderInterface;
use Altair\Events\Event;
use Altair\Events\EventKind;
use Altair\Events\EventRecordingLogger;
use Altair\Events\EventStatus;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class EventRecordingLoggerTest extends TestCase
{
    public function testErrorLevelRecordsAnHttpErrorEvent(): void
    {
        $recorder = $this->recorder();
        $logger = new EventRecordingLogger($recorder);

        $logger->error('boom', [
            'method' => 'POST',
            'path' => '/orders',
            'status' => 500,
            'exception' => new RuntimeException('boom'),
        ]);

        $this->assertCount(1, $recorder->events);
        $event = $recorder->events[0];
        $this->assertSame(EventKind::HttpError, $event->kind);
        $this->assertSame(EventStatus::Fail, $event->status);
        $this->assertSame('POST /orders', $event->command);
        $this->assertSame('boom', $event->error);
        $this->assertSame('POST', $event->extra['method']);
        $this->assertSame('/orders', $event->extra['path']);
        $this->assertSame(500, $event->extra['status']);
        $this->assertSame(RuntimeException::class, $event->extra['exception']);
    }

    public function testNonErrorLevelsAreIgnored(): void
    {
        $recorder = $this->recorder();
        $logger = new EventRecordingLogger($recorder);

        $logger->info('just fyi');
        $logger->warning('careful');
        $logger->notice('noted');
        $logger->debug('trace');

        $this->assertSame([], $recorder->events);
    }

    public function testCriticalAndAboveAreRecorded(): void
    {
        $recorder = $this->recorder();
        $logger = new EventRecordingLogger($recorder);

        $logger->critical('db down');
        $logger->alert('page someone');
        $logger->emergency('on fire');

        $this->assertCount(3, $recorder->events);
    }

    public function testEmptyMessageFallsBackToExceptionClass(): void
    {
        $recorder = $this->recorder();
        $logger = new EventRecordingLogger($recorder);

        $logger->error('', ['exception' => new RuntimeException()]);

        $this->assertSame(RuntimeException::class, $recorder->events[0]->error);
        // No request context supplied: command still non-empty.
        $this->assertSame('http.request', $recorder->events[0]->command);
    }

    /**
     * @return RecorderInterface&object{events: list<Event>}
     */
    private function recorder(): RecorderInterface
    {
        return new class () implements RecorderInterface {
            /** @var list<Event> */
            public array $events = [];

            public function record(Event $event): void
            {
                $this->events[] = $event;
            }
        };
    }
}
