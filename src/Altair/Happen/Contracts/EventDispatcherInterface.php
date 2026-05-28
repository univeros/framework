<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Happen\Contracts;

interface EventDispatcherInterface
{
    public const HIGH_PRIORITY = 100;

    public const NORMAL_PRIORITY = 0;

    public const LOW_PRIORITY = -100;

    /**
     * Add a listener for an event.
     *
     *
     */
    public function addListener(
        string $event,
        callable $listener,
        int $priority = EventDispatcherInterface::NORMAL_PRIORITY
    ): EventDispatcherInterface;

    /**
     * Use a provider to add listeners.
     *
     *
     */
    public function addListenerProvider(ListenerProviderInterface $provider): EventDispatcherInterface;

    /**
     * Adds an event subscriber.
     *
     * The subscriber is asked for all the events he is
     * interested in and added as a listener for these events.
     *
     * @param EventSubscriberInterface $subscriber The subscriber
     */
    public function addSubscriber(EventSubscriberInterface $subscriber): EventDispatcherInterface;

    /**
     * Dispatches an event to all registered listeners.
     *
     *
     */
    public function dispatch(string $name, ?EventInterface $event = null): EventInterface;

    /**
     * Dispatches a stack of events. The result is an array of EventInterface objects.
     *
     * @return list<EventInterface>
     */
    public function dispatchStack(EventStackInterface $eventStack): array;

    /**
     * Get all the listeners for an event.
     *
     * The first parameter should be the event name. We'll return an array of
     * all the registered even listeners, or an empty array if there are none.
     *
     * @return list<callable>
     */
    public function getListeners(string $event): array;

    /**
     * Check whether an event has listeners.
     *
     * The first parameter should be the event name. We'll return true if the
     * event has one or more registered even listeners, and false otherwise.
     *
     *
     */
    public function hasListeners(string $event): bool;

    /**
     * Remove all listeners for an event.
     *
     * The first parameter should be the event name. All event listeners will
     * be removed.
     *
     */
    public function removeAllListeners(string $event): EventDispatcherInterface;

    /**
     * Remove a specific listener for an event.
     *
     * The first parameter should be the event name, and the second should be
     * the event listener. It may implement the League\Event\ListenerInterface
     * or simply be "callable".
     *
     *
     */
    public function removeListener(string $event, callable $listener): EventDispatcherInterface;

    /**
     * Removes an event subscriber.
     *
     * @param EventSubscriberInterface $subscriber The subscriber
     */
    public function removeSubscriber(EventSubscriberInterface $subscriber): EventDispatcherInterface;
}
