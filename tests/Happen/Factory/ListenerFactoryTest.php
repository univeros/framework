<?php

declare(strict_types=1);

namespace Altair\Tests\Happen\Factory;

use Altair\Happen\Event;
use Altair\Happen\Factory\ListenerFactory;
use Altair\Happen\Listener\CallbackListener;
use Altair\Happen\Contracts\ListenerInterface;
use PHPUnit\Framework\TestCase;

class ListenerFactoryTest extends TestCase
{
    public function testCreateWrapsACallableInAListener(): void
    {
        $invoked = false;
        $listener = ListenerFactory::create(static function () use (&$invoked): void {
            $invoked = true;
        });

        $this->assertInstanceOf(ListenerInterface::class, $listener);
        $this->assertInstanceOf(CallbackListener::class, $listener);

        $listener(new Event('e'));

        $this->assertTrue($invoked);
    }
}
