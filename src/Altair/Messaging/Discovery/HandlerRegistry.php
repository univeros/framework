<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Messaging\Discovery;

use Altair\Messaging\Attribute\AsHandler;
use Altair\Messaging\Exception\InvalidHandlerException;

/**
 * In-memory map of message class -> ordered list of handler entries.
 *
 * Built once at boot from #[AsHandler] discovery, then queried per
 * envelope by HandlerLocator.
 */
final class HandlerRegistry
{
    /** @var array<class-string, list<HandlerEntry>> */
    private array $handlers = [];

    public function register(HandlerEntry $entry): void
    {
        $this->handlers[$entry->messageClass][] = $entry;
        usort(
            $this->handlers[$entry->messageClass],
            static fn(HandlerEntry $a, HandlerEntry $b): int => $b->priority <=> $a->priority,
        );
    }

    /**
     * @param class-string $handlerClass
     */
    public function registerFromAttribute(string $handlerClass, AsHandler $attribute): void
    {
        if (!class_exists($handlerClass)) {
            throw new InvalidHandlerException(
                \sprintf("Handler class '%s' does not exist.", $handlerClass),
            );
        }

        $this->register(new HandlerEntry(
            messageClass: $attribute->messageClass,
            handlerClass: $handlerClass,
            method: $attribute->method ?? '__invoke',
            fromTransport: $attribute->fromTransport,
            priority: $attribute->priority,
        ));
    }

    /**
     * @param  class-string         $messageClass
     * @return list<HandlerEntry>
     */
    public function handlersFor(string $messageClass): array
    {
        return $this->handlers[$messageClass] ?? [];
    }

    /**
     * @return array<class-string, list<HandlerEntry>>
     */
    public function all(): array
    {
        return $this->handlers;
    }

    public function isEmpty(): bool
    {
        return $this->handlers === [];
    }
}
