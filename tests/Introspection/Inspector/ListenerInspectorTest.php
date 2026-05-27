<?php

declare(strict_types=1);

namespace Altair\Tests\Introspection\Inspector;

use Altair\Happen\Contracts\EventDispatcherInterface;
use Altair\Happen\Contracts\EventInterface;
use Altair\Happen\EventDispatcher;
use Altair\Introspection\Exception\IntrospectionException;
use Altair\Introspection\Exception\NotFoundException;
use Altair\Introspection\Inspector\ListenerInspector;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ListenerInspector::class)]
#[CoversClass(EventDispatcher::class)]
class ListenerInspectorTest extends TestCase
{
    public function testInspectAllListsRegisteredEvents(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('user.created', static fn(): null => null);
        $dispatcher->addListener('user.created', static fn(): null => null);
        $dispatcher->addListener('order.placed', static fn(): null => null);

        $table = (new ListenerInspector($dispatcher))->inspectAll();
        $counts = [];
        foreach ($table->rows as $row) {
            $counts[$row['event']] = $row['listeners'];
        }

        $this->assertSame(['order.placed' => 1, 'user.created' => 2], $counts);
    }

    public function testInspectOneReturnsListenersInPriorityOrder(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('user.created', static fn(): string => 'low', EventDispatcherInterface::LOW_PRIORITY);
        $dispatcher->addListener('user.created', static fn(): string => 'high', EventDispatcherInterface::HIGH_PRIORITY);

        $table = (new ListenerInspector($dispatcher))->inspectOne('user.created');
        $positions = array_column($table->rows, 'position');
        $this->assertSame([0, 1], $positions);
    }

    public function testInspectOneThrowsOnUnknownEvent(): void
    {
        $this->expectException(NotFoundException::class);
        (new ListenerInspector(new EventDispatcher()))->inspectOne('does.not.exist');
    }

    public function testRejectsForeignDispatcher(): void
    {
        $foreign = new class implements EventDispatcherInterface {
            #[Override]
            public function addListener(string $name, callable $listener, int $priority = self::NORMAL_PRIORITY): EventDispatcherInterface
            {
                return $this;
            }

            #[Override]
            public function addListenerProvider($provider): EventDispatcherInterface
            {
                return $this;
            }

            #[Override]
            public function addSubscriber($subscriber): EventDispatcherInterface
            {
                return $this;
            }

            #[Override]
            public function dispatch(string $name, ?EventInterface $event = null): EventInterface
            {
                throw new \RuntimeException('not implemented');
            }

            #[Override]
            public function dispatchStack($eventStack): array
            {
                return [];
            }

            #[Override]
            public function getListeners(string $name): array
            {
                return [];
            }

            #[Override]
            public function hasListeners(string $name): bool
            {
                return false;
            }

            #[Override]
            public function removeListener(string $name, callable $listener): EventDispatcherInterface
            {
                return $this;
            }

            #[Override]
            public function removeAllListeners(string $name): EventDispatcherInterface
            {
                return $this;
            }

            #[Override]
            public function removeSubscriber($subscriber): EventDispatcherInterface
            {
                return $this;
            }
        };

        $this->expectException(IntrospectionException::class);
        new ListenerInspector($foreign);
    }
}
