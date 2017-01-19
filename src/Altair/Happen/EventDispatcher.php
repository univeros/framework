<?php
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
     * @inheritdoc
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
     * @inheritdoc
     */
    public function addListenerProvider(ListenerProviderInterface $provider): EventDispatcherInterface
    {
        $provider->provideListeners($this);

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function addSubscriber(EventSubscriberInterface $subscriber): EventDispatcherInterface
    {
        foreach ($subscriber->getSubscribedEvents() as $name => $params) {
            if (is_string($params)) {
                $this->addListener($name, ListenerFactory::create([$subscriber, $params]));
            } elseif (is_string($params[0])) {
                list($method, $priority) = $params;
                $this->addListener($name, ListenerFactory::create([$subscriber, $method]), $priority?? 0);
            } else {
                foreach ($params as $listener) {
                    list($method, $priority) = $listener;
                    $this->addListener($name, ListenerFactory::create([$subscriber, $method]), $priority?? 0);
                }
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function dispatch(string $name, EventInterface $event = null): EventInterface
    {
        $event = $event ?? (new Event($name));

        $this
            ->invokeListeners($name, $event)
            ->invokeListeners('*', $event);

        return $event;
    }

    /**
     * @inheritdoc
     */
    public function dispatchStack(EventStackInterface $eventStack): array
    {
        $events = [];
        foreach ($eventStack->getStack() as $event) {
            $events[] = $event instanceof EventInterface
                ? $this->dispatch($event->getName(), $event)
                : $this->dispatch($event);
        }

        return $events;
    }

    /**
     * @inheritdoc
     */
    public function getListeners(string $name): array
    {
        return $this->sortedListeners[$name]?? ($this->sortedListeners = $this->getSortedListeners($name));
    }

    /**
     * @inheritdoc
     */
    public function hasListeners(string $name): bool
    {
        return !(!isset($this->listeners[$name]) || count($this->listeners[$name]) === 0);
    }

    /**
     * @inheritdoc
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
     * @inheritdoc
     */
    public function removeAllListeners(string $name): EventDispatcherInterface
    {
        if ($this->hasListeners($name)) {
            unset($this->listeners[$name], $this->sortedListeners[$name]);
        }

        return $this;
    }

    /**
     * @inheritdoc
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
    protected function invokeListeners(string $name, EventInterface $event)
    {
        $listeners = $this->getListeners($name);

        foreach ($listeners as $listener) {
            if ($event->isPropagationStopped()) {
                break;
            }
            $callable = $listener instanceof ListenerInterface
                ? [$listener, '__invoke']
                : [$listener, null];

            call_user_func($callable, $event);
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

        return call_user_func_array('array_merge', $listeners);
    }
}
