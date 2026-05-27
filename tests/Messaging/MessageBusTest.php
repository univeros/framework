<?php

declare(strict_types=1);

namespace Altair\Tests\Messaging;

use Altair\Messaging\MessageBus;
use Altair\Tests\Messaging\Fixtures\PingMessage;
use Override;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface as SymfonyMessageBusInterface;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Messenger\Stamp\StampInterface;

class MessageBusTest extends TestCase
{
    public function testDispatchDelegatesToSymfonyBus(): void
    {
        $captured = [];
        $delegate = new class($captured) implements SymfonyMessageBusInterface {
            /**
             * @param array<int, array{message: object, stamps: list<StampInterface>}> $captured
             */
            public function __construct(private array &$captured) {}

            #[Override]
            public function dispatch(object $message, array $stamps = []): Envelope
            {
                $this->captured[] = ['message' => $message, 'stamps' => $stamps];

                return new Envelope($message, $stamps);
            }
        };

        $bus = new MessageBus($delegate);
        $envelope = $bus->dispatch(new PingMessage('hi'), [new BusNameStamp('default')]);

        $this->assertCount(1, $captured);
        $this->assertInstanceOf(PingMessage::class, $envelope->getMessage());
        $this->assertNotNull($envelope->last(BusNameStamp::class));
    }
}
