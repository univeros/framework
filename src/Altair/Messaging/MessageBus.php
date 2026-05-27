<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Messaging;

use Altair\Messaging\Contracts\MessageBusInterface;
use Override;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface as SymfonyMessageBusInterface;

/**
 * Thin wrapper around Symfony Messenger's bus. The framework's
 * MessageBusInterface is intentionally narrower so application code
 * never imports Symfony types directly.
 */
final readonly class MessageBus implements MessageBusInterface
{
    public function __construct(private SymfonyMessageBusInterface $bus) {}

    #[Override]
    public function dispatch(object $message, array $stamps = []): Envelope
    {
        return $this->bus->dispatch($message, $stamps);
    }
}
