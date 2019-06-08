<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Happen;

use Altair\Happen\Factory\ListenerFactory;

class EventDispatcher implements EventDispatcherInterface
{
    /**
     * @var array keeps reference of the registered listeners.
     */
    protected $listeners = [];
    /**
     * @var array keeps reference of sorted listeners by priority.
     */
    protected $sortedListeners = [];

    /**
     * @inheritDoc
     */
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
    public function addListenerProvider(ListenerProviderInterface $provider): EventDispatcherInterface
    {
        $provider->provideListeners($this);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function addSubscriber(EventSubscriberInterface $subscriber): EventDispatcherInterface
    {
        foreach ($subscriber->getSubscribedEvents() as $name => $params) {
            if (is_string($params)) {
                $this->addListener($name, ListenerFactory::create([$subscriber, $params]));
            } elseif (is_string($params[0])) {
                [$method, $priority] = $params;
                $this->addListener($name, ListenerFactory::create([$subscriber, $method]), $priority?? 0);
            } else {
                foreach ($params as $listener) {
                    [$method, $priority] = $listener;
                    $this->addListener($name, ListenerFactory::create([$subscriber, $method]), $priority?? 0);
                }
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function dispatch(string $name, EventInterface $event = null): EventInterface
    {
        $event = $event ?? new Event($name);

        $this
            ->invokeListeners($name, $event)
            ->invokeListeners('*', $event);

        return $event;
    }

    /**
     * @inheritDoc
     */
    public function dispatchStack(EventStackInterface $eventStack): array
    {
        $events = [];
        foreach ($eventStack->getStack() as $event) {
            $events[] = $event instanceof EventInterface
                ? $this->dispatch($event->getName(), $event)
                : $this->dispatch((string)$event);
        }

        return $events;
    }

    /**
     * @inheritDoc
     */
    public function getListeners(string $name): array
    {
        return $this->sortedListeners[$name]?? ($this->sortedListeners = $this->getSortedListeners($name));
    }

    /**
     * @inheritDoc
     */
    public function hasListeners(string $name): bool
    {
        return !(!isset($this->listeners[$name]) || count($this->listeners[$name]) === 0);
    }

    /**
     * @inheritDoc
     */
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
    public function removeSubscriber(EventSubscriberInterface $subscriber): EventDispatcherInterface
    {
        foreach ($subscriber->getSubscribedEvents() as $name => $params) {
            if (is_array($params) && is_array($params[0])) {
                foreach ($params as $listener) {
                    $this->removeListener($name, [$subscriber, $listener[0]]);
                }
            } else {
                $this->removeListener($name, [$subscriber, is_string($params) ? $params : $params[0]]);
            }
        }
    }

    /**
     * Invokes all listeners of an event.
     *
     * @param string $name
     * @param EventInterface $event
     *
     * @return $this
     */
    protected function invokeListeners(string $name, EventInterface $event): self
    {
        $listeners = $this->getListeners($name);

        foreach ($listeners as $listener) {
            if ($event->isPropagationStopped()) {
                break;
            }
            $callable = $listener instanceof ListenerInterface
                ? [$listener, '__invoke']
                : [$listener, null];

            $callable($event);
        }

        return $this;
    }

    /**
     * Sorts the internal list of listeners for the given event by priority.
     *
     * @param string $name The name of the event
     *
     * @return array
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
