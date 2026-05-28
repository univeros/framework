<?php

declare(strict_types=1);

namespace Altair\Tests\Happen;

use Altair\Happen\Psr14ListenerProvider;
use Altair\Tests\Happen\Fixtures\BaseEvent;
use Altair\Tests\Happen\Fixtures\DerivedEvent;
use Altair\Tests\Happen\Fixtures\MarkerInterface;
use Altair\Tests\Happen\Fixtures\UnrelatedEvent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Psr14ListenerProvider::class)]
final class Psr14ListenerProviderTest extends TestCase
{
    /**
     * @return list<callable>
     */
    private function listenersFor(Psr14ListenerProvider $provider, object $event): array
    {
        return iterator_to_array($provider->getListenersForEvent($event), false);
    }

    public function testReturnsNoListenersWhenNoneRegistered(): void
    {
        $provider = new Psr14ListenerProvider();

        $this->assertSame([], $this->listenersFor($provider, new UnrelatedEvent()));
    }

    public function testReturnsListenerRegisteredForTheEventType(): void
    {
        $provider = new Psr14ListenerProvider();
        $listener = static function (BaseEvent $e): void {
        };

        $provider->listen(BaseEvent::class, $listener);

        $this->assertSame([$listener], $this->listenersFor($provider, new BaseEvent()));
    }

    public function testDoesNotReturnListenersForUnrelatedEvents(): void
    {
        $provider = new Psr14ListenerProvider();
        $provider->listen(BaseEvent::class, static function (): void {
        });

        $this->assertSame([], $this->listenersFor($provider, new UnrelatedEvent()));
    }

    public function testSubclassEventReceivesParentClassListeners(): void
    {
        $provider = new Psr14ListenerProvider();
        $listener = static function (BaseEvent $e): void {
        };
        $provider->listen(BaseEvent::class, $listener);

        $this->assertSame([$listener], $this->listenersFor($provider, new DerivedEvent()));
    }

    public function testInterfaceListenersMatchImplementingEvents(): void
    {
        $provider = new Psr14ListenerProvider();
        $listener = static function (MarkerInterface $e): void {
        };
        $provider->listen(MarkerInterface::class, $listener);

        $this->assertSame([$listener], $this->listenersFor($provider, new BaseEvent()));
    }

    public function testListenersAreOrderedByPriorityHighestFirst(): void
    {
        $provider = new Psr14ListenerProvider();
        $normal = static function (): void {
        };
        $high = static function (): void {
        };
        $low = static function (): void {
        };

        $provider->listen(BaseEvent::class, $normal);
        $provider->listen(BaseEvent::class, $high, Psr14ListenerProvider::HIGH_PRIORITY);
        $provider->listen(BaseEvent::class, $low, Psr14ListenerProvider::LOW_PRIORITY);

        $this->assertSame([$high, $normal, $low], $this->listenersFor($provider, new BaseEvent()));
    }

    public function testEqualPriorityListenersKeepRegistrationOrder(): void
    {
        $provider = new Psr14ListenerProvider();
        $first = static function (): void {
        };
        $second = static function (): void {
        };

        $provider->listen(BaseEvent::class, $first);
        $provider->listen(BaseEvent::class, $second);

        $this->assertSame([$first, $second], $this->listenersFor($provider, new BaseEvent()));
    }

    public function testListenersAcrossMatchingTypesAreMergedAndSortedByPriority(): void
    {
        $provider = new Psr14ListenerProvider();
        $baseNormal = static function (): void {
        };
        $derivedHigh = static function (): void {
        };

        $provider->listen(BaseEvent::class, $baseNormal);
        $provider->listen(DerivedEvent::class, $derivedHigh, Psr14ListenerProvider::HIGH_PRIORITY);

        $this->assertSame([$derivedHigh, $baseNormal], $this->listenersFor($provider, new DerivedEvent()));
    }

    public function testListenReturnsSelfForChaining(): void
    {
        $provider = new Psr14ListenerProvider();

        $result = $provider->listen(BaseEvent::class, static function (): void {
        });

        $this->assertSame($provider, $result);
    }
}
