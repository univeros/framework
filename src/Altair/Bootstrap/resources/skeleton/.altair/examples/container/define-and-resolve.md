---
title: Register a singleton and resolve it via the container
scenario: Bind a service constructor to the DI container so it can be resolved with its dependencies auto-wired.
packages: [container]
since: 2.0.0
tested_by: tests/Examples/ContainerDefineAndResolveTest.php
---

# Register a singleton and resolve it via the container

`Altair\Container\Container` is the framework's DI container. The three operations you reach for daily: `singleton()` to register a shared service, `get()` to resolve it as a singleton, `make()` to build a fresh instance even when shared.

```php
use Altair\Container\Container;

final class Counter
{
    public int $count = 0;

    public function increment(): void
    {
        $this->count++;
    }
}

$container = new Container();
$container->singleton(Counter::class, static fn(): Counter => new Counter());

$first  = $container->get(Counter::class);
$second = $container->get(Counter::class);

// Both resolve to the same instance — `singleton()` made the binding shared.
// $first === $second;

$first->increment();
// $second->count === 1;
```

## Gotchas

- **`get()` honours `singleton()` / `->shared()`; `make()` does not.** `make()` always constructs a fresh instance and is the right primitive when you want isolated copies (e.g. each test, each request scope).
- **Auto-wiring beats hand-wired factories.** If `Counter` had no dependencies you could skip the factory and call `$container->singleton(Counter::class)` — the container reflects the constructor and builds an instance.
- **`->shared()` on a `factory()` binding is equivalent to `singleton()`.** Pick `singleton()` for new code; it reads better.
- **`make()` accepts positional / named params for constructor overrides** — `$container->make(Foo::class, ['name' => 'bar'])` injects a one-off value without binding it globally.
