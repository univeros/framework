<?php

declare(strict_types=1);

namespace Altair\Tests\Messaging;

use Altair\Container\Container;
use Altair\Messaging\Discovery\HandlerEntry;
use Altair\Messaging\Discovery\HandlerRegistry;
use Altair\Messaging\HandlerLocator;
use Altair\Tests\Messaging\Fixtures\SendWelcomeEmail;
use Altair\Tests\Messaging\Fixtures\SendWelcomeEmailHandler;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Handler\HandlerDescriptor;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

class HandlerLocatorTest extends TestCase
{
    public function testReturnsDescriptorForMatchingMessage(): void
    {
        $container = new Container();
        $registry = new HandlerRegistry();
        $registry->register(new HandlerEntry(
            messageClass: SendWelcomeEmail::class,
            handlerClass: SendWelcomeEmailHandler::class,
        ));

        $locator = new HandlerLocator($container, $registry);
        $envelope = new Envelope(new SendWelcomeEmail('u1', 'a@b.test'));

        $descriptors = iterator_to_array($locator->getHandlers($envelope), false);
        $this->assertCount(1, $descriptors);
        $this->assertInstanceOf(HandlerDescriptor::class, $descriptors[0]);
        $this->assertSame(SendWelcomeEmailHandler::class, $descriptors[0]->getOption('alias'));
    }

    public function testSkipsHandlersBoundToOtherTransports(): void
    {
        $container = new Container();
        $registry = new HandlerRegistry();
        $registry->register(new HandlerEntry(
            messageClass: SendWelcomeEmail::class,
            handlerClass: SendWelcomeEmailHandler::class,
            fromTransport: 'high',
        ));

        $locator = new HandlerLocator($container, $registry);
        $envelope = (new Envelope(new SendWelcomeEmail('u1', 'a@b.test')))->with(new ReceivedStamp('low'));

        $descriptors = iterator_to_array($locator->getHandlers($envelope), false);
        $this->assertSame([], $descriptors);
    }

    public function testAcceptsHandlerBoundToReceivedTransport(): void
    {
        $container = new Container();
        $registry = new HandlerRegistry();
        $registry->register(new HandlerEntry(
            messageClass: SendWelcomeEmail::class,
            handlerClass: SendWelcomeEmailHandler::class,
            fromTransport: 'high',
        ));

        $locator = new HandlerLocator($container, $registry);
        $envelope = (new Envelope(new SendWelcomeEmail('u1', 'a@b.test')))->with(new ReceivedStamp('high'));

        $descriptors = iterator_to_array($locator->getHandlers($envelope), false);
        $this->assertCount(1, $descriptors);
    }

    public function testReturnsEmptyForUnknownMessage(): void
    {
        $container = new Container();
        $registry = new HandlerRegistry();

        $locator = new HandlerLocator($container, $registry);
        $envelope = new Envelope(new SendWelcomeEmail('u1', 'a@b.test'));

        $this->assertSame([], iterator_to_array($locator->getHandlers($envelope), false));
    }
}
