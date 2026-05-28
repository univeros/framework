<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Happen;

use Altair\Happen\Contracts\EventDispatcherInterface;
use Altair\Happen\Contracts\EventInterface;
use Altair\Happen\Contracts\EventStackInterface;
use Altair\Happen\Contracts\EventSubscriberInterface;
use Altair\Happen\Contracts\ListenerProviderInterface;
use Override;

class EventDispatcher implements EventDispatcherInterface
{
    /**
     * @var array<string, array<int, array<int, callable>>> keeps reference of the registered listeners.
     */
    protected $listeners = [];

    /**
     * @var array<string, list<callable>> keeps reference of sorted listeners by priority.
     */
    protected $sortedListeners = [];

    /**
     * @inheritDoc
     */
    #[Override]
    public function addListener(
        string $name,
        callable $listener,
        int $priority = EventDispatcherInterface::NORMAL_PRIORITY
    ): EventDispatcherInterface {
        $this->listeners[$name][$priority][] = $listener;
        unset($this->sortedListeners[$name]);

        return $this;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function addListenerProvider(ListenerProviderInterface $provider): EventDispatcherInterface
    {
        $provider->provideListeners($this);

        return $this;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function addSubscriber(EventSubscriberInterface $subscriber): EventDispatcherInterface
    {
        foreach ($subscriber->getSubscribedEvents() as $name => $params) {
            foreach ($this->normalizeSubscribedEvent($params) as [$method, $priority]) {
                $listener = [$subscriber, $method];
                if (\is_callable($listener)) {
                    $this->addListener($name, $listener, $priority);
                }
            }
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function dispatch(string $name, ?EventInterface $event = null): EventInterface
    {
        $event ??= new Event($name);

        $this
            ->invokeListeners($name, $event)
            ->invokeListeners('*', $event);

        return $event;
    }

    /**
     * @inheritDoc
     *
     * @return list<EventInterface>
     */
    #[Override]
    public function dispatchStack(EventStackInterface $eventStack): array
    {
        $events = [];
        foreach ($eventStack->getStack() as $event) {
            $events[] = $event instanceof EventInterface
                ? $this->dispatch($event->getName(), $event)
                : $this->dispatch((string) $event);
        }

        return $events;
    }

    /**
     * @inheritDoc
     *
     * @return list<callable>
     */
    #[Override]
    public function getListeners(string $name): array
    {
        return $this->sortedListeners[$name] ??= $this->getSortedListeners($name);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function hasListeners(string $name): bool
    {
        return isset($this->listeners[$name]) && $this->listeners[$name] !== [];
    }

    /**
     * Every event name with at least one registered listener, sorted alphabetically.
     *
     * Read-only introspection — does not dispatch events.
     *
     * @return list<string>
     */
    public function getEventNames(): array
    {
        $names = array_keys(array_filter(
            $this->listeners,
            static fn(array $priorityBuckets): bool => $priorityBuckets !== [],
        ));
        sort($names);

        return $names;
    }

    /**
     * Number of listeners currently registered for the given event name.
     *
     * Read-only introspection — does not dispatch the event.
     */
    public function listenerCount(string $name): int
    {
        if (!isset($this->listeners[$name])) {
            return 0;
        }

        $total = 0;
        foreach ($this->listeners[$name] as $priorityBucket) {
            $total += \count($priorityBucket);
        }

        return $total;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function removeListener(string $name, callable $listener): EventDispatcherInterface
    {
        if (!isset($this->listeners[$name])) {
            return $this;
        }

        foreach ($this->listeners[$name] as $priority => $listeners) {
            if (false !== ($key = array_search($listener, $listeners, true))) {
                unset($this->listeners[$name][$priority][$key], $this->sortedListeners[$name]);
            }
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function removeAllListeners(string $name): EventDispatcherInterface
    {
        if ($this->hasListeners($name)) {
            unset($this->listeners[$name], $this->sortedListeners[$name]);
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function removeSubscriber(EventSubscriberInterface $subscriber): EventDispatcherInterface
    {
        foreach ($subscriber->getSubscribedEvents() as $name => $params) {
            foreach ($this->normalizeSubscribedEvent($params) as [$method]) {
                $listener = [$subscriber, $method];
                if (\is_callable($listener)) {
                    $this->removeListener($name, $listener);
                }
            }
        }

        return $this;
    }

    /**
     * Normalizes a single entry of {@see EventSubscriberInterface::getSubscribedEvents()}
     * into a list of method/priority pairs.
     *
     * @param string|array{0: string, 1?: int}|list<array{0: string, 1?: int}> $params
     *
     * @return list<array{0: string, 1: int}>
     */
    protected function normalizeSubscribedEvent(string|array $params): array
    {
        if (\is_string($params)) {
            return [[$params, EventDispatcherInterface::NORMAL_PRIORITY]];
        }

        $first = $params[0];

        if (\is_string($first)) {
            $priority = $params[1] ?? EventDispatcherInterface::NORMAL_PRIORITY;

            return [[$first, \is_int($priority) ? $priority : EventDispatcherInterface::NORMAL_PRIORITY]];
        }

        $normalized = [];
        foreach ($params as $listener) {
            if (!\is_array($listener)) {
                continue;
            }

            $priority = $listener[1] ?? EventDispatcherInterface::NORMAL_PRIORITY;
            $normalized[] = [$listener[0], \is_int($priority) ? $priority : EventDispatcherInterface::NORMAL_PRIORITY];
        }

        return $normalized;
    }

    /**
     * Invokes all listeners of an event.
     *
     *
     * @return $this
     */
    protected function invokeListeners(string $name, EventInterface $event): self
    {
        foreach ($this->getListeners($name) as $listener) {
            if ($event->isPropagationStopped()) {
                break;
            }

            $listener($event);
        }

        return $this;
    }

    /**
     * Sorts the internal list of listeners for the given event by priority.
     *
     * @param string $name The name of the event
     *
     * @return list<callable>
     */
    protected function getSortedListeners(string $name): array
    {
        if (!$this->hasListeners($name)) {
            return [];
        }

        $listeners = $this->listeners[$name];
        krsort($listeners);

        return array_merge(...$listeners);
    }
}
