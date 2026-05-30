---
title: Dispatch a domain event and react to it
scenario: A domain operation completes; you want one or more side effects to fire without coupling them to the caller.
packages: [happen]
since: 2.0.0
tested_by: tests/Examples/HappenDispatchADomainEventTest.php
---

# Dispatch a domain event and react to it

`Altair\Happen\EventDispatcher` is the PSR-14 dispatcher. Register listeners against a string event name; dispatch by that name with an `Event` payload. The dispatcher returns the (possibly mutated) event so the caller can inspect the post-handlers state.

```php
use Altair\Happen\Event;
use Altair\Happen\EventDispatcher;

$dispatcher = new EventDispatcher();

$delivered = [];
$dispatcher->addListener('user.registered', static function (Event $event) use (&$delivered): void {
    $delivered[] = $event->getArgument('email');
});

$dispatcher->dispatch('user.registered', new Event('user.registered', ['email' => 'jane@example.com']));

// $delivered === ['jane@example.com'];
```

## Gotchas

- **The string event name and the `Event`'s name should match.** The dispatcher only routes by the dispatched name, but readers of the payload often assume `$event->getName() === 'user.registered'`. Mixing them up is a silent bug.
- **`addListener` is FIFO; priority is set by `addListener($name, $listener, $priority)`.** Higher priorities run first.
- **Listeners receive the same `Event` instance.** Use `withArgument()` and re-pass to mutate — `Event` itself stays mostly immutable.
- For a stoppable event, call `$event->stopPropagation()` in a listener; the dispatcher checks `isPropagationStopped()` before invoking the next listener.
