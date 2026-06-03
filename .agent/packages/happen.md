# univeros/happen  ·  Altair\Happen

**Purpose:** PSR-14 event dispatcher with priority ordering, subscriber grouping, provider-based registration, wildcard listeners, and batch dispatch.

## Public contracts

| Interface | Method | Returns | Notes |
|---|---|---|---|
| `EventDispatcherInterface` | `addListener(string, callable, int)` | `EventDispatcherInterface` | constants: `HIGH_PRIORITY`, `LOW_PRIORITY`, `NORMAL_PRIORITY` |
|  | `addListenerProvider(ListenerProviderInterface)` | `EventDispatcherInterface` |  |
|  | `addSubscriber(EventSubscriberInterface)` | `EventDispatcherInterface` |  |
|  | `dispatch(string, EventInterface\|null)` | `EventInterface` |  |
|  | `dispatchStack(EventStackInterface)` | `array` |  |
|  | `getListeners(string)` | `array` |  |
|  | `hasListeners(string)` | `bool` |  |
|  | `removeAllListeners(string)` | `EventDispatcherInterface` |  |
|  | `removeListener(string, callable)` | `EventDispatcherInterface` |  |
|  | `removeSubscriber(EventSubscriberInterface)` | `EventDispatcherInterface` |  |
| `EventInterface` | `getArgument(string)` | `mixed` | extends `StoppableEventInterface` |
|  | `getArguments()` | `array` |  |
|  | `getName()` | `string` |  |
|  | `getOccurredOn()` | `int` |  |
|  | `hasArgument(string)` | `bool` |  |
|  | `isPropagationStopped()` | `bool` |  |
|  | `stopPropagation()` | `EventInterface` |  |
|  | `withArgument(string, mixed)` | `EventInterface` |  |
|  | `withArguments(array)` | `EventInterface` |  |
|  | `withName(string)` | `EventInterface` |  |
| `EventStackInterface` | `addEvent(EventInterface\|string)` | `EventStackInterface` |  |
|  | `getStack()` | `array` |  |
| `EventSubscriberInterface` | `getSubscribedEvents()` | `array` |  |
| `ListenerInterface` | `__invoke(EventInterface)` | `void` |  |
| `ListenerProviderInterface` | `provideListeners(EventDispatcherInterface)` | `ListenerProviderInterface` |  |

## Concrete classes

- `CallbackListener` — implements `ListenerInterface`
- `Event` — implements `EventInterface`, `StoppableEventInterface`
- `EventDispatcher` — implements `EventDispatcherInterface`
- `ListenerFactory`
- `Psr14EventDispatcher` _(final)_ — implements `EventDispatcherInterface`
- `Psr14ListenerProvider` _(final)_ — implements `ListenerProviderInterface`

## Tests as documentation

- `tests/Happen/EventDispatcherTest.php`
- `tests/Happen/EventTest.php`
- `tests/Happen/Factory/ListenerFactoryTest.php`
- `tests/Happen/Listener/CallbackListenerTest.php`
- `tests/Happen/Psr14EventDispatcherTest.php`
- `tests/Happen/Psr14ListenerProviderTest.php`

## Related packages

- `psr/event-dispatcher`
