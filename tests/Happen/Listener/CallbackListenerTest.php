<?php

declare(strict_types=1);

namespace Altair\Tests\Happen\Listener;

use Altair\Happen\Event;
use Altair\Happen\Contracts\EventInterface;
use Altair\Happen\Listener\CallbackListener;
use Altair\Happen\Contracts\ListenerInterface;
use PHPUnit\Framework\TestCase;

class CallbackListenerTest extends TestCase
{
    public function testInvokeCallsTheWrappedCallbackWithTheEvent(): void
    {
        $received = null;
        $listener = new CallbackListener(static function (EventInterface $e) use (&$received): void {
            $received = $e;
        });

        $event = new Event('user.created');
        $listener($event);

        $this->assertSame($event, $received);
    }

    public function testImplementsListenerInterface(): void
    {
        $listener = new CallbackListener(static fn() => null);

        $this->assertInstanceOf(ListenerInterface::class, $listener);
    }
}
