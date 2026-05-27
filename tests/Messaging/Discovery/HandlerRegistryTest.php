<?php

declare(strict_types=1);

namespace Altair\Tests\Messaging\Discovery;

use Altair\Messaging\Attribute\AsHandler;
use Altair\Messaging\Discovery\HandlerEntry;
use Altair\Messaging\Discovery\HandlerRegistry;
use Altair\Messaging\Exception\InvalidHandlerException;
use Altair\Tests\Messaging\Fixtures\PingMessage;
use Altair\Tests\Messaging\Fixtures\SendWelcomeEmail;
use Altair\Tests\Messaging\Fixtures\SendWelcomeEmailHandler;
use PHPUnit\Framework\TestCase;

class HandlerRegistryTest extends TestCase
{
    public function testStartsEmpty(): void
    {
        $registry = new HandlerRegistry();
        $this->assertTrue($registry->isEmpty());
        $this->assertSame([], $registry->handlersFor(SendWelcomeEmail::class));
    }

    public function testRegisterAddsHandler(): void
    {
        $registry = new HandlerRegistry();
        $registry->register(new HandlerEntry(
            messageClass: SendWelcomeEmail::class,
            handlerClass: SendWelcomeEmailHandler::class,
        ));

        $this->assertFalse($registry->isEmpty());
        $entries = $registry->handlersFor(SendWelcomeEmail::class);
        $this->assertCount(1, $entries);
        $this->assertSame(SendWelcomeEmailHandler::class, $entries[0]->handlerClass);
    }

    public function testHigherPrioritySortsFirst(): void
    {
        $registry = new HandlerRegistry();
        $registry->register(new HandlerEntry(
            messageClass: PingMessage::class,
            handlerClass: 'AppA\\Handler',
            priority: 1,
        ));
        $registry->register(new HandlerEntry(
            messageClass: PingMessage::class,
            handlerClass: 'AppB\\Handler',
            priority: 10,
        ));

        $entries = $registry->handlersFor(PingMessage::class);
        $this->assertSame('AppB\\Handler', $entries[0]->handlerClass);
        $this->assertSame('AppA\\Handler', $entries[1]->handlerClass);
    }

    public function testRegisterFromAttributeRejectsMissingClass(): void
    {
        $this->expectException(InvalidHandlerException::class);

        $registry = new HandlerRegistry();
        $registry->registerFromAttribute(
            'Nonexistent\\HandlerClass',
            new AsHandler(SendWelcomeEmail::class),
        );
    }

    public function testRegisterFromAttributeStoresMethodAndTransport(): void
    {
        $registry = new HandlerRegistry();
        $registry->registerFromAttribute(
            SendWelcomeEmailHandler::class,
            new AsHandler(
                messageClass: SendWelcomeEmail::class,
                fromTransport: 'high',
                priority: 5,
            ),
        );

        $entry = $registry->handlersFor(SendWelcomeEmail::class)[0];
        $this->assertSame('high', $entry->fromTransport);
        $this->assertSame(5, $entry->priority);
        $this->assertSame('__invoke', $entry->method);
    }
}
