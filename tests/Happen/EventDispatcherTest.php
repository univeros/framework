<?php

declare(strict_types=1);

namespace Altair\Tests\Happen;

use Altair\Happen\Event;
use Altair\Happen\EventDispatcher;
use Altair\Happen\Contracts\EventDispatcherInterface;
use Altair\Happen\Contracts\EventInterface;
use Altair\Happen\Contracts\EventStackInterface;
use Altair\Happen\Contracts\EventSubscriberInterface;
use Altair\Happen\Contracts\ListenerProviderInterface;
use Altair\Happen\Traits\EventStackAwareTrait;
use PHPUnit\Framework\TestCase;

class EventDispatcherTest extends TestCase
{
    public function testHasListenersIsFalseByDefault(): void
    {
        $dispatcher = new EventDispatcher();

        $this->assertFalse($dispatcher->hasListeners('user.created'));
    }

    public function testAddListenerRegistersTheListener(): void
    {
        $dispatcher = new EventDispatcher();
        $listener = fn(EventInterface $e): null => null;

        $dispatcher->addListener('user.created', $listener);

        $this->assertTrue($dispatcher->hasListeners('user.created'));
        $this->assertContains($listener, $dispatcher->getListeners('user.created'));
    }

    public function testDispatchInvokesRegisteredListenersWithTheEvent(): void
    {
        $received = null;
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('user.created', static function (EventInterface $e) use (&$received): void {
            $received = $e;
        });

        $event = new Event('user.created', ['id' => 1]);
        $result = $dispatcher->dispatch('user.created', $event);

        $this->assertSame($event, $result);
        $this->assertSame($event, $received);
    }

    public function testDispatchCreatesADefaultEventWhenNoneIsGiven(): void
    {
        $received = null;
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('user.created', static function (EventInterface $e) use (&$received): void {
            $received = $e;
        });

        $dispatcher->dispatch('user.created');

        $this->assertInstanceOf(EventInterface::class, $received);
        $this->assertSame('user.created', $received->getName());
    }

    public function testListenersAreInvokedInPriorityOrderHighestFirst(): void
    {
        $callOrder = [];
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('e', static function () use (&$callOrder): void {
            $callOrder[] = 'normal';
        });
        $dispatcher->addListener('e', static function () use (&$callOrder): void {
            $callOrder[] = 'high';
        }, EventDispatcherInterface::HIGH_PRIORITY);
        $dispatcher->addListener('e', static function () use (&$callOrder): void {
            $callOrder[] = 'low';
        }, EventDispatcherInterface::LOW_PRIORITY);

        $dispatcher->dispatch('e');

        $this->assertSame(['high', 'normal', 'low'], $callOrder);
    }

    public function testStopPropagationPreventsRemainingListenersFromRunning(): void
    {
        $callOrder = [];
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('e', static function (EventInterface $e) use (&$callOrder): void {
            $callOrder[] = 'first';
            $e->stopPropagation();
        });
        $dispatcher->addListener('e', static function () use (&$callOrder): void {
            $callOrder[] = 'second';
        });

        $dispatcher->dispatch('e');

        $this->assertSame(['first'], $callOrder);
    }

    public function testWildcardListenersAreInvokedAfterNamedOnes(): void
    {
        $callOrder = [];
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('user.created', static function () use (&$callOrder): void {
            $callOrder[] = 'named';
        });
        $dispatcher->addListener('*', static function () use (&$callOrder): void {
            $callOrder[] = 'wildcard';
        });

        $dispatcher->dispatch('user.created');

        $this->assertSame(['named', 'wildcard'], $callOrder);
    }

    public function testRemoveListenerRemovesIt(): void
    {
        $dispatcher = new EventDispatcher();
        $listener = fn(EventInterface $e): null => null;
        $dispatcher->addListener('user.created', $listener);

        $dispatcher->removeListener('user.created', $listener);

        $this->assertNotContains($listener, $dispatcher->getListeners('user.created'));
    }

    public function testRemoveListenerOnUnknownNameIsNoOp(): void
    {
        $dispatcher = new EventDispatcher();

        $result = $dispatcher->removeListener('does-not-exist', fn(): null => null);

        $this->assertSame($dispatcher, $result);
    }

    public function testRemoveAllListenersClearsThemForGivenEvent(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('user.created', fn(): null => null);
        $dispatcher->addListener('user.created', fn(): null => null);

        $dispatcher->removeAllListeners('user.created');

        $this->assertFalse($dispatcher->hasListeners('user.created'));
    }

    public function testDispatchStackDispatchesEachEnqueuedEvent(): void
    {
        $callOrder = [];
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('a', static function () use (&$callOrder): void {
            $callOrder[] = 'a';
        });
        $dispatcher->addListener('b', static function () use (&$callOrder): void {
            $callOrder[] = 'b';
        });

        $stack = new class () implements EventStackInterface {
            use EventStackAwareTrait;
        };
        $stack->addEvent('a')->addEvent(new Event('b'));

        $events = $dispatcher->dispatchStack($stack);

        $this->assertCount(2, $events);
        $this->assertSame(['a', 'b'], $callOrder);
    }

    public function testAddSubscriberAddsAllItsListeners(): void
    {
        $dispatcher = new EventDispatcher();
        $subscriber = new class () implements EventSubscriberInterface {
            public array $calls = [];

            public function getSubscribedEvents(): array
            {
                return [
                    'user.created' => 'onCreated',
                    'user.updated' => ['onUpdated', EventDispatcherInterface::HIGH_PRIORITY],
                ];
            }

            public function onCreated(EventInterface $e): void
            {
                $this->calls[] = 'created';
            }

            public function onUpdated(EventInterface $e): void
            {
                $this->calls[] = 'updated';
            }
        };

        $dispatcher->addSubscriber($subscriber);
        $dispatcher->dispatch('user.created');
        $dispatcher->dispatch('user.updated');

        $this->assertSame(['created', 'updated'], $subscriber->calls);
    }

    public function testRemoveSubscriberRemovesItsListeners(): void
    {
        $dispatcher = new EventDispatcher();
        $subscriber = new class () implements EventSubscriberInterface {
            public function getSubscribedEvents(): array
            {
                return ['user.created' => 'onCreated'];
            }

            public function onCreated(EventInterface $e): void
            {
            }
        };

        $dispatcher->addSubscriber($subscriber);
        $dispatcher->removeSubscriber($subscriber);

        $this->assertEmpty($dispatcher->getListeners('user.created'));
    }

    public function testAddListenerProviderInvokesItsProvideListenersMethod(): void
    {
        $dispatcher = new EventDispatcher();
        $provider = new class () implements ListenerProviderInterface {
            public bool $called = false;

            public function provideListeners(EventDispatcherInterface $acceptor): ListenerProviderInterface
            {
                $this->called = true;
                $acceptor->addListener('seeded', static fn(): null => null);

                return $this;
            }
        };

        $dispatcher->addListenerProvider($provider);

        $this->assertTrue($provider->called);
        $this->assertTrue($dispatcher->hasListeners('seeded'));
    }
}
