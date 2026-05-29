<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Messaging;

use Altair\Container\Container;
use Altair\Messaging\Discovery\HandlerEntry;
use Altair\Messaging\Discovery\HandlerRegistry;
use Altair\Messaging\Exception\InvalidHandlerException;
use Override;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Handler\HandlerDescriptor;
use Symfony\Component\Messenger\Handler\HandlersLocatorInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

/**
 * Resolves message handlers through the framework's Container, so
 * handler classes get the same DI treatment as any other service.
 *
 * Implements Symfony's HandlersLocatorInterface so it slots into
 * HandleMessageMiddleware unchanged.
 */
final readonly class HandlerLocator implements HandlersLocatorInterface
{
    public function __construct(
        private Container $container,
        private HandlerRegistry $registry,
    ) {}

    /**
     * @return iterable<HandlerDescriptor>
     */
    #[Override]
    public function getHandlers(Envelope $envelope): iterable
    {
        $message = $envelope->getMessage();
        $received = $envelope->last(ReceivedStamp::class);
        $receivedFrom = $received?->getTransportName();

        foreach ($this->registry->handlersFor($message::class) as $entry) {
            if (!$this->matchesTransport($entry, $receivedFrom)) {
                continue;
            }

            yield new HandlerDescriptor(
                $this->resolveCallable($entry),
                $this->descriptorOptions($entry),
            );
        }
    }

    /**
     * @return callable(object): mixed
     */
    private function resolveCallable(HandlerEntry $entry): callable
    {
        $instance = $this->container->make($entry->handlerClass);

        if (!method_exists($instance, $entry->method)) {
            throw new InvalidHandlerException(
                \sprintf(
                    "Handler '%s' is missing method '%s' declared by #[AsHandler].",
                    $entry->handlerClass,
                    $entry->method,
                ),
            );
        }

        /** @var callable(object): mixed $callable */
        $callable = [$instance, $entry->method];

        return $callable;
    }

    private function matchesTransport(HandlerEntry $entry, ?string $receivedFrom): bool
    {
        if ($entry->fromTransport === null) {
            return true;
        }

        return $entry->fromTransport === $receivedFrom;
    }

    /**
     * @return array<string, mixed>
     */
    private function descriptorOptions(HandlerEntry $entry): array
    {
        $options = ['alias' => $entry->handlerClass];

        if ($entry->fromTransport !== null) {
            $options['from_transport'] = $entry->fromTransport;
        }

        if ($entry->method !== '__invoke') {
            $options['method'] = $entry->method;
        }

        return $options;
    }
}
