<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Messaging\Transport;

use Altair\Container\Container;
use Override;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBus as SymfonyMessageBus;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Lazy bus reference passed to SyncTransportFactory to break the
 * construction cycle (bus -> transports -> sync factory -> bus).
 *
 * Symfony's SyncTransport only calls dispatch() at message-arrival
 * time, so resolving the real bus on first dispatch is safe.
 */
final readonly class LazyBus implements MessageBusInterface
{
    public function __construct(private Container $container) {}

    #[Override]
    public function dispatch(object $message, array $stamps = []): Envelope
    {
        /** @var SymfonyMessageBus $bus */
        $bus = $this->container->make(SymfonyMessageBus::class);

        return $bus->dispatch($message, $stamps);
    }
}
