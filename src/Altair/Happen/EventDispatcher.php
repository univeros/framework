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
     * @var array<string, array<int, list<callable>>> keeps reference of the registered listeners.
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
            if (\is_string($params)) {
                $this->addListener($name, [$subscriber, $params]);
            } elseif (\is_string($params[0])) {
                [$method, $priority] = $params + [null, 0];
                $this->addListener($name, [$subscriber, $method], $priority ?? 0);
            } else {
                foreach ($params as $listener) {
                    [$method, $priority] = $listener + [null, 0];
                    $this->addListener($name, [$subscriber, $method], $priority ?? 0);
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
        return isset($this->listeners[$name]) && \count($this->listeners[$name]) !== 0;
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
            if (\is_array($params) && \is_array($params[0])) {
                foreach ($params as $listener) {
                    $this->removeListener($name, [$subscriber, $listener[0]]);
                }
            } else {
                $this->removeListener($name, [$subscriber, \is_string($params) ? $params : $params[0]]);
            }
        }

        return $this;
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
