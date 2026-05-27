<?php

declare(strict_types=1);

namespace Altair\Tests\Messaging\Integration;

use Altair\Container\Container;
use Altair\Messaging\Discovery\HandlerRegistry;
use Altair\Messaging\HandlerLocator;
use Altair\Messaging\MessageBus;
use Altair\Messaging\Middleware\ContainerHandlerMiddleware;
use Altair\Tests\Messaging\Fixtures\SendWelcomeEmail;
use Altair\Tests\Messaging\Fixtures\SendWelcomeEmailHandler;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\EventListener\StopWorkerOnMessageLimitListener;
use Symfony\Component\Messenger\MessageBus as SymfonyMessageBus;
use Symfony\Component\Messenger\Middleware\SendMessageMiddleware;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Messenger\Transport\Sender\SendersLocator;
use Symfony\Component\Messenger\Worker;

class DispatchAndConsumeTest extends TestCase
{
    public function testDispatchSyncBus(): void
    {
        $handler = new SendWelcomeEmailHandler();

        $container = new Container();
        $container->share($handler);

        $registry = new HandlerRegistry();
        $registry->registerFromAttribute(
            SendWelcomeEmailHandler::class,
            new \Altair\Messaging\Attribute\AsHandler(SendWelcomeEmail::class),
        );

        $locator = new HandlerLocator($container, $registry);
        $handle = new ContainerHandlerMiddleware($locator, allowNoHandlers: true);

        $bus = new MessageBus(new SymfonyMessageBus([$handle]));

        $bus->dispatch(new SendWelcomeEmail('u1', 'alice@example.test'));
        $bus->dispatch(new SendWelcomeEmail('u2', 'bob@example.test'));

        $this->assertCount(2, $handler->received);
        $this->assertSame('u1', $handler->received[0]->userId);
        $this->assertSame('u2', $handler->received[1]->userId);
    }

    public function testDispatchAsyncViaInMemoryTransportThenConsume(): void
    {
        $handler = new SendWelcomeEmailHandler();

        $container = new Container();
        $container->share($handler);

        $registry = new HandlerRegistry();
        $registry->registerFromAttribute(
            SendWelcomeEmailHandler::class,
            new \Altair\Messaging\Attribute\AsHandler(SendWelcomeEmail::class),
        );

        $locator = new HandlerLocator($container, $registry);
        $handle = new ContainerHandlerMiddleware($locator, allowNoHandlers: true);

        $transport = new InMemoryTransport();
        $sendersLocator = new SendersLocator(
            [SendWelcomeEmail::class => ['default']],
            new class($transport) implements \Psr\Container\ContainerInterface {
                public function __construct(private readonly InMemoryTransport $transport) {}

                public function get(string $id): InMemoryTransport
                {
                    return $this->transport;
                }

                public function has(string $id): bool
                {
                    return $id === 'default';
                }
            },
        );

        $send = new SendMessageMiddleware($sendersLocator);
        $bus = new SymfonyMessageBus([$send, $handle]);

        $bus->dispatch(new SendWelcomeEmail('u9', 'queued@example.test'));
        $this->assertSame([], $handler->received, 'Handler must not have fired on dispatch with async transport.');
        $this->assertCount(1, $transport->getSent());

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new StopWorkerOnMessageLimitListener(1));
        $worker = new Worker(['default' => $transport], $bus, $dispatcher);
        $worker->run(['sleep' => 0]);

        $this->assertCount(1, $handler->received);
        $this->assertSame('u9', $handler->received[0]->userId);
    }
}
