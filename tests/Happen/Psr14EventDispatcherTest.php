<?php

declare(strict_types=1);

namespace Altair\Tests\Happen;

use Altair\Happen\Psr14EventDispatcher;
use Altair\Happen\Psr14ListenerProvider;
use Altair\Tests\Happen\Fixtures\BaseEvent;
use Altair\Tests\Happen\Fixtures\DerivedEvent;
use Altair\Tests\Happen\Fixtures\StoppableEvent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Psr14EventDispatcher::class)]
final class Psr14EventDispatcherTest extends TestCase
{
    public function testDispatchReturnsTheSameEventInstance(): void
    {
        $dispatcher = new Psr14EventDispatcher(new Psr14ListenerProvider());
        $event = new BaseEvent();

        $this->assertSame($event, $dispatcher->dispatch($event));
    }

    public function testDispatchInvokesMatchingListenerWithTheEvent(): void
    {
        $received = null;
        $provider = new Psr14ListenerProvider();
        $provider->listen(BaseEvent::class, static function (BaseEvent $e) use (&$received): void {
            $received = $e;
        });
        $dispatcher = new Psr14EventDispatcher($provider);

        $event = new BaseEvent();
        $dispatcher->dispatch($event);

        $this->assertSame($event, $received);
    }

    public function testDispatchInvokesNothingWhenNoListenersMatch(): void
    {
        $dispatcher = new Psr14EventDispatcher(new Psr14ListenerProvider());
        $event = new BaseEvent();

        $this->assertSame($event, $dispatcher->dispatch($event));
    }

    public function testDispatchInvokesListenersInPriorityOrder(): void
    {
        $calls = [];
        $provider = new Psr14ListenerProvider();
        $provider->listen(BaseEvent::class, static function () use (&$calls): void {
            $calls[] = 'normal';
        });
        $provider->listen(BaseEvent::class, static function () use (&$calls): void {
            $calls[] = 'high';
        }, Psr14ListenerProvider::HIGH_PRIORITY);
        $provider->listen(BaseEvent::class, static function () use (&$calls): void {
            $calls[] = 'low';
        }, Psr14ListenerProvider::LOW_PRIORITY);
        $dispatcher = new Psr14EventDispatcher($provider);

        $dispatcher->dispatch(new BaseEvent());

        $this->assertSame(['high', 'normal', 'low'], $calls);
    }

    public function testStoppableEventHaltsRemainingListeners(): void
    {
        $calls = [];
        $provider = new Psr14ListenerProvider();
        $provider->listen(StoppableEvent::class, static function (StoppableEvent $e) use (&$calls): void {
            $calls[] = 'first';
            $e->stop();
        });
        $provider->listen(StoppableEvent::class, static function () use (&$calls): void {
            $calls[] = 'second';
        });
        $dispatcher = new Psr14EventDispatcher($provider);

        $dispatcher->dispatch(new StoppableEvent());

        $this->assertSame(['first'], $calls);
    }

    public function testNonStoppableEventInvokesAllListeners(): void
    {
        $calls = [];
        $provider = new Psr14ListenerProvider();
        $provider->listen(BaseEvent::class, static function () use (&$calls): void {
            $calls[] = 'first';
        });
        $provider->listen(BaseEvent::class, static function () use (&$calls): void {
            $calls[] = 'second';
        });
        $dispatcher = new Psr14EventDispatcher($provider);

        $dispatcher->dispatch(new BaseEvent());

        $this->assertSame(['first', 'second'], $calls);
    }

    public function testAlreadyStoppedEventInvokesNoListeners(): void
    {
        $calls = [];
        $provider = new Psr14ListenerProvider();
        $provider->listen(StoppableEvent::class, static function () use (&$calls): void {
            $calls[] = 'listener';
        });
        $dispatcher = new Psr14EventDispatcher($provider);

        $dispatcher->dispatch(new StoppableEvent(stopped: true));

        $this->assertSame([], $calls);
    }

    public function testInheritanceMatchingDispatchesParentListenersToSubclass(): void
    {
        $calls = [];
        $provider = new Psr14ListenerProvider();
        $provider->listen(BaseEvent::class, static function () use (&$calls): void {
            $calls[] = 'base';
        });
        $dispatcher = new Psr14EventDispatcher($provider);

        $dispatcher->dispatch(new DerivedEvent());

        $this->assertSame(['base'], $calls);
    }

    public function testListenerRegisteredDuringDispatchDoesNotFireInTheSameCycle(): void
    {
        $calls = [];
        $provider = new Psr14ListenerProvider();
        $provider->listen(BaseEvent::class, static function () use ($provider, &$calls): void {
            $calls[] = 'first';
            $provider->listen(BaseEvent::class, static function () use (&$calls): void {
                $calls[] = 'registered-during-dispatch';
            });
        });
        $dispatcher = new Psr14EventDispatcher($provider);

        $dispatcher->dispatch(new BaseEvent());

        $this->assertSame(['first'], $calls);
    }
}
