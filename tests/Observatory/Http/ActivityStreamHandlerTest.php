<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Tests\Observatory\Http;

use Altair\Events\Actor;
use Altair\Events\Contracts\EventStorageInterface;
use Altair\Events\Event;
use Altair\Events\EventKind;
use Altair\Events\EventStatus;
use Altair\Events\Reader;
use Altair\Observatory\Http\ActivityStreamHandler;
use Altair\Observatory\Observatory;
use Altair\Observatory\Panel\RuntimePanel;
use Altair\Observatory\PanelRegistry;
use Altair\Observatory\Security\EnvironmentAccessGuard;
use DateTimeImmutable;
use Generator;
use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\StreamFactory;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ActivityStreamHandler::class)]
final class ActivityStreamHandlerTest extends TestCase
{
    public function testStreamsRecentEventsOldestFirstWhenAccessible(): void
    {
        $response = $this->handler(enabled: true, events: [$this->event('A'), $this->event('B')])
            ->handle(new ServerRequest());

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('text/event-stream', $response->getHeaderLine('Content-Type'));

        $body = (string) $response->getBody();
        self::assertStringContainsString('retry: 2000', $body);
        self::assertStringContainsString('event: activity', $body);
        self::assertStringContainsString('id: A', $body);
        self::assertStringContainsString('id: B', $body);
        // Emitted oldest-first so the client prepends and keeps newest on top.
        self::assertLessThan(strpos($body, 'id: B'), strpos($body, 'id: A'));
    }

    public function testLastEventIdReturnsOnlyNewerEvents(): void
    {
        $request = (new ServerRequest())->withHeader('Last-Event-ID', 'A');
        $body = (string) $this->handler(true, [$this->event('A'), $this->event('B')])->handle($request)->getBody();

        self::assertStringContainsString('id: B', $body);
        self::assertStringNotContainsString('id: A', $body);
    }

    public function testReturns403WhenDenied(): void
    {
        $response = $this->handler(enabled: false, events: [$this->event('A')])->handle(new ServerRequest());

        self::assertSame(403, $response->getStatusCode());
    }

    /**
     * @param list<Event> $events
     */
    private function handler(bool $enabled, array $events): ActivityStreamHandler
    {
        $observatory = new Observatory(
            new PanelRegistry([new RuntimePanel()]),
            new EnvironmentAccessGuard($enabled, 'local'),
        );

        return new ActivityStreamHandler(
            $observatory,
            new Reader($this->storage($events)),
            new ResponseFactory(),
            new StreamFactory(),
        );
    }

    /**
     * @param list<Event> $events oldest -> newest
     */
    private function storage(array $events): EventStorageInterface
    {
        return new class ($events) implements EventStorageInterface {
            /** @param list<Event> $events */
            public function __construct(private array $events) {}

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
        };
    }

    private function event(string $id): Event
    {
        return new Event(
            id: $id,
            timestamp: new DateTimeImmutable('2026-05-27T10:00:00Z'),
            actor: Actor::Cli,
            command: 'bin/altair foo',
            kind: EventKind::Scaffold,
            status: EventStatus::Ok,
            durationMs: 5,
            error: null,
        );
    }
}
