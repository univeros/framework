<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Tests\Examples;

use Altair\Happen\Event;
use Altair\Happen\EventDispatcher;
use PHPUnit\Framework\TestCase;

/**
 * Runs the snippet from .altair/examples/happen/dispatch-a-domain-event.md
 * end-to-end. Asserts that listeners receive the dispatched event and read
 * arguments through `getArgument()`.
 */
final class HappenDispatchADomainEventTest extends TestCase
{
    public function testListenersReceiveDispatchedEvent(): void
    {
        $dispatcher = new EventDispatcher();

        $delivered = [];
        $dispatcher->addListener('user.registered', static function (Event $event) use (&$delivered): void {
            $delivered[] = $event->getArgument('email');
        });

        $dispatcher->dispatch('user.registered', new Event('user.registered', ['email' => 'jane@example.com']));

        self::assertSame(['jane@example.com'], $delivered);
    }

    public function testStopPropagationPreventsLaterListenersFromRunning(): void
    {
        $dispatcher = new EventDispatcher();
        $invoked = [];

        $dispatcher->addListener('user.registered', static function (Event $event) use (&$invoked): void {
            $invoked[] = 'first';
            $event->stopPropagation();
        });
        $dispatcher->addListener('user.registered', static function () use (&$invoked): void {
            $invoked[] = 'second';
        });

        $dispatcher->dispatch('user.registered', new Event('user.registered', ['email' => 'x@y.z']));

        self::assertSame(['first'], $invoked);
    }
}
