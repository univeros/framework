<?php
namespace Altair\Happen;

interface EventDispatcherInterface
{
    const HIGH_PRIORITY = 100;
    const NORMAL_PRIORITY = 0;
    const LOW_PRIORITY = -100;

    /**
     * Add a listener for an event.
     *
     * @param string $event
     * @param callable $listener
     * @param int $priority
     *
     * @return EventDispatcherInterface
     */
    public function addListener(
        string $event,
        callable $listener,
        int $priority = EventDispatcherInterface::NORMAL_PRIORITY
    ): EventDispatcherInterface;

    /**
     * Use a provider to add listeners.
     *
     * @param ListenerProviderInterface $provider
     *
     * @return EventDispatcherInterface
     */
    public function addListenerProvider(ListenerProviderInterface $provider): EventDispatcherInterface;

    /**
     * Adds an event subscriber.
     *
     * The subscriber is asked for all the events he is
     * interested in and added as a listener for these events.
     *
     * @param EventSubscriberInterface $subscriber The subscriber
     *
     * @return EventDispatcherInterface
     */
    public function addSubscriber(EventSubscriberInterface $subscriber): EventDispatcherInterface;

    /**
     * Dispatches an event to all registered listeners.
     *
     * @param string $name
     * @param EventInterface $event
     *
     * @return EventInterface
     */
    public function dispatch(string $name, EventInterface $event = null): EventInterface;

    /**
     * Dispatches a stack of events. The result is an array of EventInterface objects.
     *
     * @param EventStackInterface $eventStack
     *
     * @return array
     */
    public function dispatchStack(EventStackInterface $eventStack): array;

    /**
     * Get all the listeners for an event.
     *
     * The first parameter should be the event name. We'll return an array of
     * all the registered even listeners, or an empty array if there are none.
     *
     * @param string $event
     *
     * @return array
     */
    public function getListeners(string $event): array;

    /**
     * Check whether an event has listeners.
     *
     * The first parameter should be the event name. We'll return true if the
     * event has one or more registered even listeners, and false otherwise.
     *
     * @param string $event
     *
     * @return bool
     */
    public function hasListeners(string $event): bool;

    /**
     * Remove all listeners for an event.
     *
     * The first parameter should be the event name. All event listeners will
     * be removed.
     *
     * @param string $event
     *
     * @return EventDispatcherInterface;
     */
    public function removeAllListeners(string $event): EventDispatcherInterface;

    /**
     * Remove a specific listener for an event.
     *
     * The first parameter should be the event name, and the second should be
     * the event listener. It may implement the League\Event\ListenerInterface
     * or simply be "callable".
     *
     * @param string $event
     * @param callable $listener
     *
     * @return EventDispatcherInterface
     */
    public function removeListener(string $event, callable $listener): EventDispatcherInterface;

    /**
     * Removes an event subscriber.
     *
     * @param EventSubscriberInterface $subscriber The subscriber
     *
     * @return EventDispatcherInterface
     */
    public function removeSubscriber(EventSubscriberInterface $subscriber): EventDispatcherInterface;
}
