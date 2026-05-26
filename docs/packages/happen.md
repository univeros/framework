# Happen

A PSR-14-compatible event dispatcher with priority ordering, subscriber grouping, provider-based registration, wildcard listeners, and batch dispatch.

---

## Composer and namespace

```
Package:    univeros/happen
Namespace:  Altair\Happen
Requires:   php >=8.3, psr/event-dispatcher ^1.0
```

---

## Introduction

Event-driven code becomes hard to follow when you wire listeners at the call site, mix notification logic with business logic, or use static singletons. Happen solves each of these problems through a clean dispatcher object, a priority queue, and a provider pattern that keeps registration separate from dispatch.

Before reaching for this package, consider what kind of cross-component communication you need. Use Happen when a piece of code needs to announce that something occurred — a user registered, an order shipped, a payment failed — and it should not know or care who acts on that announcement. Use the [Courier](./courier.md) package when you need command-style semantics where one handler is expected, a return value matters, or you want a bus with middleware. Use a direct method call when the relationship is an internal implementation detail that will never need to be observed from outside the class.

Happen is PSR-14 compliant through its `EventInterface`, which extends `Psr\EventDispatcher\StoppableEventInterface`. The `Event` class implements `EventInterface` and can be passed to any PSR-14-compatible dispatcher. Any dispatcher that accepts a `Psr\EventDispatcher\StoppableEventInterface` can receive an `Event` from this package without additional adapters.

The priority model is numeric and explicit. Every listener you register receives a priority integer, defaulting to `0` (normal). Higher integers fire earlier. The dispatcher exposes three named constants for the common cases: `HIGH_PRIORITY` (100), `NORMAL_PRIORITY` (0), and `LOW_PRIORITY` (−100). Within a single priority level, listeners fire in registration order. This determinism is important in validation chains and in audit scenarios where observer order has meaning.

The provider concept separates "who knows about listeners" from "who dispatches events". A `ListenerProviderInterface` implementation receives the dispatcher and calls `addListener` on it. This lets you group registration by domain, load listener sets from container configuration, or swap a test provider in unit tests without touching the dispatcher.

---

## Installation

```bash
composer require univeros/happen
```

The only runtime dependency is `psr/event-dispatcher ^1.0`. No database, no framework container, no extension is required.

---

## Quick start

This example shows the minimal path: define an event, attach a listener, and dispatch. The dispatcher returns the event object so you can inspect its final state.

```php
<?php

declare(strict_types=1);

use Altair\Happen\Event;
use Altair\Happen\EventDispatcher;
use Altair\Happen\Contracts\EventInterface;

$dispatcher = new EventDispatcher();

// Register a listener for the 'user.created' event.
$dispatcher->addListener('user.created', function (EventInterface $event): void {
    $userId = $event->getArgument('id');
    echo "Welcome, user #{$userId}!\n";
});

// Build the event object and dispatch it.
$event = new Event('user.created', ['id' => 42]);
$dispatcher->dispatch('user.created', $event);
```

If you omit the event object, `dispatch` creates a bare `Event` for you using the given name.

```php
$dispatcher->dispatch('user.created'); // creates Event('user.created') internally
```

---

## Concepts

### Event objects

An event is any object that implements `EventInterface`. The concrete `Event` class covers most use cases directly, but you can extend it or implement the interface yourself. Every event carries:

- a **name** (string) — the dispatch key
- an **arguments** map (array) — arbitrary key/value payload, always lowercased on read
- an **occurredOn** timestamp (int) — set to midnight UTC today on construction via `Carbon::today('UTC')`
- a **propagationStopped** flag (bool) — false by default

Arguments are case-insensitive. `$event->getArgument('UserId')` retrieves the value stored under `userid`. This normalisation happens inside both `hasArgument` and `getArgument`.

`Event` follows the immutability convention of this framework. `withName`, `withArgument`, and `withArguments` each return a new cloned instance. The original event is never mutated.

### Listener callables

Any PHP `callable` is a valid listener. The dispatcher accepts closures, static methods, invokable objects, and first-class callable syntax. The callable receives the `EventInterface` object as its sole argument.

When you need to wrap an arbitrary callable in a typed `ListenerInterface` object — for example, to store it in a container as a tagged service — use `ListenerFactory::create`:

```php
use Altair\Happen\Factory\ListenerFactory;

$listener = ListenerFactory::create(static fn(EventInterface $e) => doSomething($e));
```

`ListenerFactory::create` returns a `CallbackListener`, which implements `ListenerInterface` and is itself callable.

### Subscribers

A subscriber is a class that declares multiple listeners in one place. It implements `EventSubscriberInterface`, which requires a single `getSubscribedEvents(): array` method. The return value maps event names to method names and optional priorities.

The dispatcher's `addSubscriber` method reads that map and calls `addListener` for each entry. `removeSubscriber` reverses the process exactly.

### ListenerProvider

A `ListenerProviderInterface` implementation is a registration unit. Its one method, `provideListeners(EventDispatcherInterface $acceptor)`, receives the dispatcher and calls `addListener` or `addSubscriber` on it. When you pass a provider to `addListenerProvider`, the dispatcher immediately calls `provideListeners` with itself as the argument.

Multiple providers can be added to a single dispatcher. Each one contributes its listeners independently. This lets you split registration by bounded context, package, or test fixture.

### Dispatcher

`EventDispatcher` is the central object. It manages the sorted listener registry, drives dispatch, and implements fluent chaining — every mutating method returns `$this`. The internal registry is a two-level map: `$listeners[eventName][priority][]`. The sorted cache is invalidated whenever a new listener is added to an event, ensuring the sort is always fresh.

### Priority queue ordering

Listeners are sorted by priority descending (highest number first) at the moment they are first retrieved, then cached. Within a priority bucket, listeners fire in the order they were registered. The sort is performed once per event name per change to that event's listener set.

### Stoppable events

Calling `$event->stopPropagation()` on an event inside a listener prevents any remaining listeners from running. The dispatcher checks `isPropagationStopped()` before each listener invocation and breaks out of the loop early.

Note that stopping propagation on named listeners (e.g. `user.created`) does not suppress wildcard listeners. The dispatcher invokes named-event listeners first, then wildcard (`*`) listeners in a separate pass. A stopped event from the named pass carries that stopped state into the wildcard pass, so wildcard listeners also break early.

### Wildcard listeners

Any listener registered under the name `'*'` is invoked for every event, after that event's named listeners finish. Register global concerns — logging, metrics, audit trails — as wildcard listeners so they do not have to be attached individually to every event name.

---

## Usage

### Defining event objects

Extend `Event` when you want a named class with constructor-enforced arguments. The parent constructor accepts the event name and an optional arguments array.

```php
<?php

declare(strict_types=1);

namespace App\Domain\Order;

use Altair\Happen\Event;

// A domain event with typed constructor arguments compiled into the payload.
final class OrderShipped extends Event
{
    public function __construct(int $orderId, string $shippedAt)
    {
        parent::__construct('order.shipped', [
            'order_id'   => $orderId,
            'shipped_at' => $shippedAt,
        ]);
    }
}
```

You can also use the base `Event` class directly with an inline arguments array when a named class adds no clarity.

### Registering listeners (priority, ordering)

Pass a priority as the third argument to `addListener`. The default is `EventDispatcherInterface::NORMAL_PRIORITY` (0). Higher values fire first.

```php
use Altair\Happen\EventDispatcher;
use Altair\Happen\Contracts\EventDispatcherInterface;
use Altair\Happen\Contracts\EventInterface;

$dispatcher = new EventDispatcher();

// Runs first — validation should precede side effects.
$dispatcher->addListener(
    'order.shipped',
    fn(EventInterface $e) => validateShipment($e),
    EventDispatcherInterface::HIGH_PRIORITY
);

// Runs second — normal business logic.
$dispatcher->addListener(
    'order.shipped',
    fn(EventInterface $e) => notifyWarehouse($e)
);

// Runs last — analytics is low priority.
$dispatcher->addListener(
    'order.shipped',
    fn(EventInterface $e) => recordMetric($e),
    EventDispatcherInterface::LOW_PRIORITY
);
```

To remove a specific listener, pass the same callable reference you used when adding it.

```php
$listener = fn(EventInterface $e) => doThing($e);
$dispatcher->addListener('order.shipped', $listener);

// Later, remove just that one listener.
$dispatcher->removeListener('order.shipped', $listener);

// Or clear every listener for the event at once.
$dispatcher->removeAllListeners('order.shipped');
```

### Subscribers — declaring multiple listeners on a class

A subscriber keeps all listeners for a related set of events in one class, making it easy to register and unregister them together.

```php
<?php

declare(strict_types=1);

namespace App\Listener;

use Altair\Happen\Contracts\EventDispatcherInterface;
use Altair\Happen\Contracts\EventInterface;
use Altair\Happen\Contracts\EventSubscriberInterface;

final class OrderSubscriber implements EventSubscriberInterface
{
    public function getSubscribedEvents(): array
    {
        return [
            // Simple form: method name only, normal priority.
            'order.created' => 'onCreated',

            // Array form: method name + priority.
            'order.shipped' => ['onShipped', EventDispatcherInterface::HIGH_PRIORITY],

            // Multi-listener form: multiple methods for one event.
            'order.cancelled' => [
                ['onCancelledNotify'],
                ['onCancelledAudit', EventDispatcherInterface::LOW_PRIORITY],
            ],
        ];
    }

    public function onCreated(EventInterface $event): void
    {
        // handle order.created
    }

    public function onShipped(EventInterface $event): void
    {
        // handle order.shipped at high priority
    }

    public function onCancelledNotify(EventInterface $event): void
    {
        // notify customer
    }

    public function onCancelledAudit(EventInterface $event): void
    {
        // write audit record at low priority
    }
}
```

Register and unregister the subscriber as a unit.

```php
$subscriber = new OrderSubscriber();

$dispatcher->addSubscriber($subscriber);

// Later, remove all its listeners in one call.
$dispatcher->removeSubscriber($subscriber);
```

### Providers — the PSR-14 way

A provider owns listener registration logic. Implement `ListenerProviderInterface` and call `addListener` (or `addSubscriber`) inside `provideListeners`.

```php
<?php

declare(strict_types=1);

namespace App\Provider;

use Altair\Happen\Contracts\EventDispatcherInterface;
use Altair\Happen\Contracts\EventInterface;
use Altair\Happen\Contracts\ListenerProviderInterface;

final class OrderListenerProvider implements ListenerProviderInterface
{
    public function provideListeners(EventDispatcherInterface $acceptor): ListenerProviderInterface
    {
        $acceptor->addListener('order.created', fn(EventInterface $e) => handleCreated($e));
        $acceptor->addListener('order.shipped', fn(EventInterface $e) => handleShipped($e));

        return $this;
    }
}
```

Pass each provider to `addListenerProvider`. The dispatcher calls `provideListeners` immediately.

```php
$dispatcher->addListenerProvider(new OrderListenerProvider());
$dispatcher->addListenerProvider(new AuditListenerProvider());
```

Multiple providers accumulate their listeners in the same dispatcher. There is no conflict between them — they simply extend the listener registry.

### Stoppable events

Call `stopPropagation()` inside a listener to prevent subsequent listeners from running. This is useful for validation chains where the first failure should block all downstream handlers.

```php
$dispatcher->addListener('order.placed', function (EventInterface $event): void {
    if (!isStockAvailable($event->getArgument('sku'))) {
        // No further listeners will run for named event 'order.placed'.
        $event->stopPropagation();
    }
}, EventDispatcherInterface::HIGH_PRIORITY);

$dispatcher->addListener('order.placed', function (EventInterface $event): void {
    // This never runs if stock check fails.
    reserveStock($event->getArgument('sku'));
});
```

After dispatch, inspect the stopped state to decide how to respond.

```php
$result = $dispatcher->dispatch('order.placed', $event);

if ($result->isPropagationStopped()) {
    // The chain was interrupted; treat it as a failure.
}
```

### Dispatching events

Pass an event name and an optional `EventInterface` object to `dispatch`. The method returns the event after all listeners have run.

```php
// Dispatch with an explicit event object.
$event = new Event('user.registered', ['email' => 'alice@example.com']);
$dispatched = $dispatcher->dispatch('user.registered', $event);

// Dispatch by name only — a bare Event is created automatically.
$dispatcher->dispatch('user.loggedin');
```

To dispatch several events in sequence, build an `EventStackInterface` and call `dispatchStack`. The method returns an array of the dispatched event objects in stack order.

```php
use Altair\Happen\Contracts\EventStackInterface;
use Altair\Happen\Traits\EventStackAwareTrait;

// Create an anonymous stack object using the provided trait.
$stack = new class () implements EventStackInterface {
    use EventStackAwareTrait;
};

$stack
    ->addEvent('order.created')                        // by name — creates bare Event
    ->addEvent(new Event('order.shipped', ['id' => 7])); // by object

$events = $dispatcher->dispatchStack($stack);
// $events[0] is the dispatched 'order.created' event.
// $events[1] is the dispatched 'order.shipped' event.
```

---

## Configuration

Happen has no `Configuration/` classes of its own. It carries no framework bootstrap requirements. You instantiate `EventDispatcher` directly, or bind it in your container.

When using the [Container](./container.md) package, define `EventDispatcherInterface` as a shared alias for `EventDispatcher`, then inject providers through the container's definition system.

```php
use Altair\Happen\EventDispatcher;
use Altair\Happen\Contracts\EventDispatcherInterface;

$container->share(EventDispatcherInterface::class, EventDispatcher::class);
```

---

## Testing

Testing event-driven code requires asserting that listeners were called and that the event carried the expected payload. A reference-capture closure is the simplest spy.

```php
use Altair\Happen\Event;
use Altair\Happen\EventDispatcher;
use Altair\Happen\Contracts\EventInterface;
use PHPUnit\Framework\TestCase;

final class OrderDispatchTest extends TestCase
{
    public function testOrderShippedListenerReceivesEvent(): void
    {
        $received = null;
        $dispatcher = new EventDispatcher();

        // Use a reference-capture closure as a spy listener.
        $dispatcher->addListener(
            'order.shipped',
            static function (EventInterface $e) use (&$received): void {
                $received = $e;
            }
        );

        $event = new Event('order.shipped', ['order_id' => 7]);
        $dispatcher->dispatch('order.shipped', $event);

        $this->assertSame($event, $received);
        $this->assertSame(7, $received->getArgument('order_id'));
    }

    public function testStopPropagationPreventsSecondListener(): void
    {
        $callOrder = [];
        $dispatcher = new EventDispatcher();

        $dispatcher->addListener('e', static function (EventInterface $e) use (&$callOrder): void {
            $callOrder[] = 'first';
            $e->stopPropagation();
        });
        $dispatcher->addListener('e', static function () use (&$callOrder): void {
            $callOrder[] = 'second';
        });

        $dispatcher->dispatch('e');

        $this->assertSame(['first'], $callOrder);
    }

    public function testListenersFireInPriorityOrder(): void
    {
        $callOrder = [];
        $dispatcher = new EventDispatcher();

        $dispatcher->addListener('e', static fn() => $callOrder[] = 'normal');
        $dispatcher->addListener('e', static fn() => $callOrder[] = 'high', 100);
        $dispatcher->addListener('e', static fn() => $callOrder[] = 'low', -100);

        $dispatcher->dispatch('e');

        $this->assertSame(['high', 'normal', 'low'], $callOrder);
    }
}
```

To assert that a provider registered its listeners, check `hasListeners` after calling `addListenerProvider`.

```php
$dispatcher->addListenerProvider(new OrderListenerProvider());

$this->assertTrue($dispatcher->hasListeners('order.created'));
$this->assertTrue($dispatcher->hasListeners('order.shipped'));
```

---

## Extending

### Custom providers

Create a provider class per bounded context. Inject any services the listeners need through the provider's constructor, then capture them via closure.

```php
final class NotificationListenerProvider implements ListenerProviderInterface
{
    public function __construct(private readonly Mailer $mailer) {}

    public function provideListeners(EventDispatcherInterface $acceptor): ListenerProviderInterface
    {
        $mailer = $this->mailer;

        $acceptor->addListener('user.registered', static function (EventInterface $e) use ($mailer): void {
            $mailer->sendWelcome($e->getArgument('email'));
        });

        return $this;
    }
}
```

### Custom dispatcher behaviour

Extend `EventDispatcher` and override `invokeListeners` to add cross-cutting concerns such as exception wrapping, timing, or tracing.

```php
final class InstrumentedDispatcher extends EventDispatcher
{
    protected function invokeListeners(string $name, EventInterface $event): self
    {
        $start = hrtime(true);
        parent::invokeListeners($name, $event);
        $elapsed = hrtime(true) - $start;
        recordMetric("event.dispatch.{$name}", $elapsed);

        return $this;
    }
}
```

### Implementing EventStackInterface without the trait

`EventStackAwareTrait` provides a ready implementation of `EventStackInterface`. If you have an existing domain object — an aggregate root collecting domain events, for example — implement `getStack` and `addEvent` directly instead.

---

## Recipes

### Domain events fired from a handler

Fire domain events after an aggregate operation succeeds. Collect them during the operation, then dispatch the stack in the application layer.

```php
// In the application handler.
$order = $orderRepository->findById($id);
$order->ship(shippedAt: new DateTimeImmutable());

// The aggregate exposes collected events via EventStackInterface.
$dispatcher->dispatchStack($order->releaseEvents());
```

The aggregate implements `EventStackInterface` using `EventStackAwareTrait`. The handler dispatches everything after the repository write is confirmed.

### Audit-log listener

Register a wildcard listener that records every event to a persistent audit log. Wildcard listeners run after named ones, so they see the final event state including any arguments added by earlier listeners.

```php
$dispatcher->addListener('*', static function (EventInterface $event) use ($auditLog): void {
    $auditLog->record(
        name:       $event->getName(),
        occurredOn: $event->getOccurredOn(),
        payload:    $event->getArguments(),
    );
}, EventDispatcherInterface::LOW_PRIORITY);
```

### Conditional dispatch

Check whether any listeners are registered before building an expensive event payload.

```php
if ($dispatcher->hasListeners('report.generated')) {
    $payload = $reportBuilder->buildSummary(); // only compute if needed
    $dispatcher->dispatch('report.generated', new Event('report.generated', $payload));
}
```

### Stoppable validation chain

Use a high-to-low priority listener stack as a validation pipeline. Each listener checks one rule and stops propagation on failure. The caller inspects the final state.

```php
$dispatcher->addListener('checkout.initiated', static function (EventInterface $e): void {
    if (!cartIsNotEmpty($e->getArgument('cart_id'))) {
        $e->withArgument('error', 'Cart is empty')->stopPropagation();
    }
}, 200);

$dispatcher->addListener('checkout.initiated', static function (EventInterface $e): void {
    if (!paymentMethodIsValid($e->getArgument('payment_method'))) {
        $e->withArgument('error', 'Invalid payment method')->stopPropagation();
    }
}, 100);

$result = $dispatcher->dispatch('checkout.initiated', $event);

if ($result->isPropagationStopped()) {
    return new ErrorResponse($result->getArgument('error'));
}
```

Note that `withArgument` returns a new instance and does not mutate the event in place. The pattern above is illustrative; in production code the listener would need to pass the new instance back to the caller through a shared reference or a dedicated result object.

### Subscriber hot-swap

Replace a subscriber at runtime without restarting the dispatcher — useful in long-running processes that reload configuration.

```php
$old = new EmailNotificationSubscriber($legacyMailer);
$new = new EmailNotificationSubscriber($updatedMailer);

$dispatcher->removeSubscriber($old);
$dispatcher->addSubscriber($new);
```

---

## Related packages

- [Courier](./courier.md) — command bus with middleware. Use Courier when one handler is expected and a return value is needed; use Happen when many observers react to a notification.
- [Container](./container.md) — PSR-11 DI container. Resolve listener classes and providers through the container to inject their dependencies cleanly.
- [Http](./http.md) — PSR-7/15 HTTP stack. Dispatch application events from HTTP middleware to decouple request handling from domain side effects.

---

## Limitations

- **Synchronous only.** `EventDispatcher` runs listeners in the same PHP request or process. There is no built-in queue, deferred dispatch, or async transport. For background processing, dispatch a job from inside a listener using your queue library of choice.
- **No event store.** Dispatched events are not persisted. If you need a replay-capable event log, record events in a wildcard listener that writes to a database or append-only log.
- **No automatic argument mutation propagation.** `withArgument` and `withArguments` return new instances. A listener that calls `$event->withArgument(...)` gets a new object but the dispatcher continues passing the original event to subsequent listeners. Mutating the shared state requires using `stopPropagation` in combination with a result envelope, or using a mutable event class that you design specifically for that purpose.
- **No PSR-14 `ListenerProviderInterface` from `psr/event-dispatcher`.** The `ListenerProviderInterface` in `Altair\Happen\Contracts` has a different signature from `Psr\EventDispatcher\ListenerProviderInterface`. The Altair interface passes the dispatcher to the provider; the PSR-14 interface takes an event object and returns an iterable of listeners. These two styles are complementary, not interchangeable.
