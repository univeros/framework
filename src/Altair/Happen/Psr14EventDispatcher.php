<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Happen;

use Override;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use Psr\EventDispatcher\StoppableEventInterface;

/**
 * PSR-14 object-based event dispatcher.
 *
 * Pairs with any PSR-14 {@see ListenerProviderInterface} (the framework ships
 * {@see Psr14ListenerProvider}). Listeners receive the event object and may
 * mutate it; the same instance is returned. When the event is a
 * {@see StoppableEventInterface}, propagation is checked before each listener
 * so a stopped event halts the remaining listeners.
 *
 * This is the object-based counterpart to the name-keyed {@see EventDispatcher};
 * the two are independent and a host binds whichever it needs.
 */
final readonly class Psr14EventDispatcher implements EventDispatcherInterface
{
    public function __construct(private ListenerProviderInterface $provider) {}

    #[Override]
    public function dispatch(object $event): object
    {
        $stoppable = $event instanceof StoppableEventInterface;

        foreach ($this->provider->getListenersForEvent($event) as $listener) {
            if ($stoppable && $event->isPropagationStopped()) {
                break;
            }

            $listener($event);
        }

        return $event;
    }
}
