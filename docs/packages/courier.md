# Courier

Courier is a synchronous command bus that routes immutable message objects to their handlers through a configurable middleware pipeline.

## Composer and namespace

- **Package:** `univeros/courier`
- **Namespace:** `Altair\Courier`
- **Requires:** PHP 8.3+, `psr/log ^3`, `univeros/configuration`, `univeros/container`, `univeros/filesystem`, `univeros/structure`

## Introduction

The command bus pattern separates intent from execution. Instead of calling a service directly, you create a message object that describes what you want done, then hand it to a bus. The bus finds the right handler and calls it. Your application code never imports the handler class.

This approach has a long lineage. Tactician (from The PHP League) popularised it in the PHP world. Symfony Messenger extends it with transport layers, retry logic, and async workers. Courier is a lighter take: no transports, no background workers, no serialization. It focuses on the synchronous dispatch case and lets you compose cross-cutting concerns through middleware.

Why use a command bus over calling services directly? First, it centralises handler resolution — you change the mapping in one place instead of updating every call site. Second, it makes middleware straightforward: wrapping every handler dispatch with logging, locking, or validation is a one-liner because every dispatch passes through the same pipeline. Third, it decouples the caller from the implementation; swapping a handler from a plain class to a container-resolved service requires no changes outside the locator configuration.

Courier expresses this design through two interchangeable execution strategies. The "exec" strategy resolves and calls a handler directly with no middleware involved. The "middleware" strategy routes the message through an ordered pipeline of `CommandMiddlewareInterface` objects before reaching the handler. You choose the strategy once, at construction time.

The package's contracts sit in `Altair\Courier\Contracts\` and cover every extension point: the bus itself, command messages, handlers, locator services, name resolvers, middleware, and the pipeline's middleware resolver. Concrete classes are thin; most interesting behaviour is in the Strategy and Middleware layers.

## Installation

```bash
composer require univeros/courier
```

There are no extension dependencies. `psr/log ^3` is required for `CommandLoggerMiddleware`; if you do not use that middleware, the interface is never called.

## Quick start

The fastest path uses the exec strategy with an in-memory map.

```php
use Altair\Courier\CommandBus;
use Altair\Courier\Contracts\CommandInterface;
use Altair\Courier\Contracts\CommandMessageInterface;
use Altair\Courier\Resolver\ClassCommandMessageNameResolver;
use Altair\Courier\Service\InMemoryCommandLocatorService;
use Altair\Courier\Strategy\CommandRunnerExecStrategy;
use Altair\Courier\Support\MessageCommandMap;

// 1. Define a message — it carries the data your handler needs.
class RegisterUserMessage implements CommandMessageInterface
{
    use \Altair\Courier\Traits\LogMessageTrait;

    public function __construct(public readonly string $email) {}

    public function getName(): string { return self::class; }
}

// 2. Define a handler — it acts on the message.
class RegisterUserCommand implements CommandInterface
{
    public function exec(CommandMessageInterface $message): void
    {
        // $message is RegisterUserMessage; write to DB, fire events, etc.
    }
}

// 3. Build the bus and dispatch.
$map      = new MessageCommandMap([RegisterUserMessage::class => RegisterUserCommand::class]);
$locator  = new InMemoryCommandLocatorService($map);
$resolver = new ClassCommandMessageNameResolver();
$strategy = new CommandRunnerExecStrategy($locator, $resolver);
$bus      = new CommandBus($strategy);

$bus->handle(new RegisterUserMessage('alice@example.com'));
```

## Concepts

### CommandMessageInterface

A message is a plain PHP object that implements `CommandMessageInterface`. It carries the data needed to fulfil a request. It is not an event — it targets one handler, and that handler is expected to execute a side effect.

The interface requires two methods:

- `getName(): string` — returns the string key used to look up the handler.
- `getLogMessage(): ?LogMessageInterface` — returns an optional log annotation the handler can attach after execution.
- `withLogMessage(LogMessageInterface): CommandMessageInterface` — returns a copy (or `$this`) with a log annotation attached.

The `LogMessageTrait` in `Altair\Courier\Traits\` provides a default implementation of both log methods. Include it in your message class to avoid boilerplate.

### CommandInterface (handler)

A handler implements `CommandInterface`, which declares one method:

```php
public function exec(CommandMessageInterface $message): void;
```

The return type is `void`. Handlers produce side effects; they do not return values. Communicate outcomes through the message's `withLogMessage` method or through injected services.

### CommandBus

`CommandBus` is a thin façade. It takes a `CommandRunnerStrategyInterface` at construction and delegates every `handle(CommandMessageInterface)` call to that strategy's `run()` method. There is no logic in `CommandBus` beyond this delegation.

### CommandRunnerStrategyInterface

Two strategies ship with the package.

`CommandRunnerExecStrategy` resolves the handler directly and calls `exec`. No middleware runs. Use this when you do not need cross-cutting concerns.

`CommandRunnerMiddlewareStrategy` maintains an ordered list of `CommandMiddlewareInterface` objects and fires them as a chain. Each middleware receives the message and a `$next` callable pointing to the following step. The last step in the chain is a no-op closure; the terminal handler middleware (`CommandHandlerMiddleware`) must appear in the list explicitly.

### CommandLocatorServiceInterface

A locator maps a string name to a `CommandInterface` instance. The interface declares `has(string): bool` and `get(string): CommandInterface`. When `get` is called with an unknown name, it throws `UnknownCommandMessageNameException`.

Two implementations are provided:

- `InMemoryCommandLocatorService` — backed by a `MessageCommandMap` (a `Map` from `univeros/structure`). Instantiates handlers lazily on first use and caches them in the map.
- `CallableCommandLocatorService` — backed by any `callable` that accepts a name and returns a `CommandInterface` or `null`. Use this to integrate with a DI container without the full `MiddlewareResolver` machinery.

### CommandMessageNameResolverInterface

The name resolver turns a message object into the string key used for locator lookup. Two resolvers are provided:

- `ClassCommandMessageNameResolver` — uses `$message::class`. The map key is the fully-qualified class name.
- `CommandMessageNameResolver` — calls `$message->getName()`. The map key is whatever string your message returns.

Choose one and use it consistently in both the locator's map and the bus configuration.

### CommandMiddlewareInterface

Middleware implements `handle(CommandMessageInterface $message, callable $next): void`. Call `$next($message)` to pass control forward. Omit the call to short-circuit the pipeline. Middleware runs in array order; the handler middleware should be last.

### MiddlewareResolverInterface

The middleware strategy can accept middleware as either objects or class-name strings. When strings are present, it calls the `MiddlewareResolverInterface` to instantiate them. `MiddlewareResolver` uses the `Altair\Container\Container` to make instances, allowing constructor injection for middleware. If no resolver is set, all entries must already be instantiated objects.

## Usage

### Defining commands and handlers

Naming conventions differ by resolver:

- With `ClassCommandMessageNameResolver`: name your message `RegisterUserMessage` and your handler `RegisterUserCommand`. The map key is `RegisterUserMessage::class`.
- With `CommandMessageNameResolver`: the map key is whatever `getName()` returns — typically a short string like `'register-user'`.

Keep messages in a `Message/` or `Command/` subdirectory and handlers alongside them or in a dedicated `Handler/` directory. There is no enforced convention; consistency within your project matters more than any external rule.

```php
// Message — data only, no behaviour.
class PlaceOrderMessage implements CommandMessageInterface
{
    use LogMessageTrait;

    public function __construct(
        public readonly string $userId,
        public readonly array  $items,
    ) {}

    public function getName(): string { return self::class; }
}

// Handler — behaviour only, receives data through the message.
class PlaceOrderCommand implements CommandInterface
{
    public function __construct(private readonly OrderRepository $orders) {}

    public function exec(CommandMessageInterface $message): void
    {
        assert($message instanceof PlaceOrderMessage);
        $this->orders->save(Order::from($message->userId, $message->items));
    }
}
```

### Dispatching through the bus

Call `$bus->handle($message)` from anywhere — a controller, a CLI command, an event listener. The bus returns `void`; side effects happen inside the handler.

```php
// Every call site looks the same regardless of which handler runs.
$bus->handle(new PlaceOrderMessage($userId, $items));
```

### The middleware pipeline

The middleware strategy fires middleware in array order before reaching the handler. Use middleware for cross-cutting concerns: locking, logging, validation, transactions.

```php
use Altair\Courier\Middleware\CommandHandlerMiddleware;
use Altair\Courier\Middleware\CommandLockerMiddleware;
use Altair\Courier\Middleware\CommandLoggerMiddleware;
use Altair\Courier\Strategy\CommandRunnerMiddlewareStrategy;

// CommandHandlerMiddleware MUST be in the list — it is the terminal handler.
$strategy = new CommandRunnerMiddlewareStrategy([
    new CommandLockerMiddleware(),
    new CommandLoggerMiddleware($logger),
    new CommandHandlerMiddleware($locator, $nameResolver),
]);

$bus = new CommandBus($strategy);
```

Three middleware ship with the package:

**CommandHandlerMiddleware** — resolves and calls the handler, then calls `$next`. It must be the last content-producing step in the pipeline.

**CommandLockerMiddleware** — ensures that commands dispatched while a handler is already running do not execute immediately. It queues them and drains the queue after the current handler finishes. This prevents re-entrant dispatch from interleaving execution. The test suite demonstrates this with `testItFinishesHandlingAMessageBeforeHandlingTheNext`.

**CommandLoggerMiddleware** — logs before and after handler execution. It accepts any `Psr\Log\LoggerInterface` and a default log level. After the handler runs, it reads `$message->getLogMessage()`. If the message carries a `LogMessageInterface` with a different level than the configured default, it uses that level for the "finished" log entry. This lets handlers signal elevated log levels (for example, `LogLevel::ERROR`) without changing the middleware configuration.

### Locator strategies

**Explicit map with `InMemoryCommandLocatorService`:** Suitable for most applications. Build a `MessageCommandMap` from an associative array and pass it to the locator. The locator instantiates handlers on first use and caches them.

```php
$map     = new MessageCommandMap([
    PlaceOrderMessage::class => PlaceOrderCommand::class,
    CancelOrderMessage::class => CancelOrderCommand::class,
]);
$locator = new InMemoryCommandLocatorService($map);
```

The `withMap` method returns a new locator instance with a different map, preserving immutability at the service level.

**Callable with `CallableCommandLocatorService`:** Pass any callable that takes a string name and returns a `CommandInterface` or `null`. The locator calls it for both `has` and `get`. Use this to delegate resolution to a container.

```php
$locator = new CallableCommandLocatorService(
    fn(string $name): ?CommandInterface => match ($name) {
        PlaceOrderMessage::class => $container->make(PlaceOrderCommand::class),
        default                  => null,
    }
);
```

### Resolver strategies

**No resolver (default):** Pass instantiated middleware objects to `CommandRunnerMiddlewareStrategy`. The strategy uses them as-is.

**`MiddlewareResolver`:** Pass class-name strings alongside or instead of objects. The resolver calls `Altair\Container\Container::make()` to instantiate them, enabling constructor injection for middleware. Pass it as the second argument to `CommandRunnerMiddlewareStrategy`.

```php
$strategy = new CommandRunnerMiddlewareStrategy(
    [
        CommandLockerMiddleware::class,   // string — resolved by container
        new CommandLoggerMiddleware($logger), // object — used directly
        CommandHandlerMiddleware::class,  // string — resolved by container
    ],
    new MiddlewareResolver($container)
);
```

The resolver is called lazily, the first time each middleware entry is needed. Once resolved, the object is stored back in the middleware list.

## Configuration

Two `ConfigurationInterface` classes wire the bus for use with `Altair\Container\Container`. Both read the command map from a file whose path is in the `COURIER_MAP_FILE` environment variable.

### ExecCommandBusConfiguration

Wires the direct-execution strategy. No middleware runs. Suitable when you want the lowest overhead and handle cross-cutting concerns elsewhere.

```php
// .env
COURIER_MAP_FILE=/path/to/command-map.php

// command-map.php — returned array maps message class to handler class.
return [
    PlaceOrderMessage::class  => PlaceOrderCommand::class,
    CancelOrderMessage::class => CancelOrderCommand::class,
];
```

Apply it to the container:

```php
use Altair\Courier\Configuration\ExecCommandBusConfiguration;

(new ExecCommandBusConfiguration())->apply($container);

// Resolve via the interface.
$bus = $container->make(CommandBusInterface::class);
```

The configuration aliases:

- `CommandMessageNameResolverInterface` → `ClassCommandMessageNameResolver`
- `CommandLocatorServiceInterface` → `InMemoryCommandLocatorService`
- `CommandRunnerStrategyInterface` → `CommandRunnerExecStrategy`
- `CommandBusInterface` → `CommandBus`

### MiddlewareCommandBusConfiguration

Wires the middleware strategy with three built-in middleware in order: `CommandLockerMiddleware`, `CommandLoggerMiddleware`, `CommandHandlerMiddleware`. The same `COURIER_MAP_FILE` variable provides the map.

`CommandLoggerMiddleware` requires a `Psr\Log\LoggerInterface` binding in the container. Register your logger before applying this configuration.

```php
use Altair\Courier\Configuration\MiddlewareCommandBusConfiguration;

$container->alias(LoggerInterface::class, MonologLogger::class);
(new MiddlewareCommandBusConfiguration())->apply($container);

$bus = $container->make(CommandBusInterface::class);
```

The configuration additionally aliases `MiddlewareResolverInterface` → `MiddlewareResolver`, so middleware class-name strings are resolved through the container.

## Testing

### Testing handlers in isolation

Handlers are plain classes. Inject a mock repository or service and call `exec` directly.

```php
public function testPlacesOrder(): void
{
    $repository = $this->createMock(OrderRepository::class);
    $repository->expects($this->once())->method('save');

    $handler = new PlaceOrderCommand($repository);
    $message = new PlaceOrderMessage('user-1', ['item-a']);
    $handler->exec($message);
}
```

### Testing through the bus

Build the bus in the test the same way you build it in production. Use a real `InMemoryCommandLocatorService` with the actual handler under test. Assert the side effect or check the message's log annotation.

```php
public function testBusDispatchesHandler(): void
{
    $map      = new MessageCommandMap([TestMessage::class => TestHandler::class]);
    $locator  = new InMemoryCommandLocatorService($map);
    $strategy = new CommandRunnerExecStrategy($locator, new ClassCommandMessageNameResolver());
    $bus      = new CommandBus($strategy);

    $message = new TestMessage();
    $bus->handle($message);

    $this->assertSame('executed', $message->result);
}
```

### Testing middleware ordering

Use `CommandLockerMiddleware` and a stub middleware to assert execution order, as the test suite does in `CommandBusTest::testItFinishesHandlingAMessageBeforeHandlingTheNext`. Append middleware with `$strategy->add(new StubMiddleware(...))` after constructing the bus to inject test behaviour without altering the production list.

### Testing `CallableCommandLocatorService`

Pass a closure or invokable class as the callable. The closure can return different handlers based on the name, allowing you to test the locator's routing without a real map.

## Extending

### Custom middleware

Implement `CommandMiddlewareInterface`:

```php
use Altair\Courier\Contracts\CommandMessageInterface;
use Altair\Courier\Contracts\CommandMiddlewareInterface;

class TransactionMiddleware implements CommandMiddlewareInterface
{
    public function __construct(private readonly Connection $db) {}

    public function handle(CommandMessageInterface $message, callable $next): void
    {
        $this->db->beginTransaction();
        try {
            $next($message);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
```

Add it to the strategy before `CommandHandlerMiddleware` so it wraps handler execution.

### Custom locator

Implement `CommandLocatorServiceInterface`. Both `has` and `get` must be consistent: if `has` returns `true`, `get` must not throw for the same name. Throw `UnknownCommandMessageNameException` when `get` is called for an unknown name; callers expect this exception type.

```php
class ContainerLocatorService implements CommandLocatorServiceInterface
{
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly array $map,
    ) {}

    public function has(string $name): bool
    {
        return isset($this->map[$name]);
    }

    public function get(string $name): CommandInterface
    {
        if (!$this->has($name)) {
            throw new UnknownCommandMessageNameException("Unknown: $name");
        }
        return $this->container->get($this->map[$name]);
    }
}
```

### Custom name resolver

Implement `CommandMessageNameResolverInterface` and return any string you use as the map key. A common pattern is to add a static method to the message class and call it here, keeping the key co-located with the message definition.

## Recipes

### Transactional middleware

Wrap `$next($message)` in a database transaction. Place this middleware after `CommandLockerMiddleware` and before `CommandHandlerMiddleware` so the lock is acquired before the transaction opens.

```php
$strategy = new CommandRunnerMiddlewareStrategy([
    new CommandLockerMiddleware(),
    new TransactionMiddleware($db),
    new CommandHandlerMiddleware($locator, $resolver),
]);
```

### Logging middleware

`CommandLoggerMiddleware` logs before and after handler execution. It reads `$message->getLogMessage()` after `$next` returns. A handler that detects an abnormal condition can attach an error-level `LogMessage` to the message; the logger picks it up and logs at that elevated level without any changes to the middleware configuration.

```php
// Inside a handler:
public function exec(CommandMessageInterface $message): void
{
    if ($this->quotaExceeded()) {
        $message->withLogMessage(new LogMessage('Quota exceeded', LogLevel::WARNING));
        return;
    }
    // normal path
}
```

### Validation pipeline

Add a validation middleware before the handler. Throw or attach a log message on failure. Because the pipeline is synchronous, throwing an exception in middleware prevents `$next` from running and stops handler execution.

```php
class ValidationMiddleware implements CommandMiddlewareInterface
{
    public function handle(CommandMessageInterface $message, callable $next): void
    {
        if ($message instanceof Validatable) {
            $message->validate(); // throws ValidationException on failure
        }
        $next($message);
    }
}
```

### Container-resolved middleware

When middleware have constructor dependencies, register them in the container and pass their class names as strings alongside the `MiddlewareResolver`. The resolver calls `Container::make()` on first use.

```php
$strategy = new CommandRunnerMiddlewareStrategy(
    [
        CommandLockerMiddleware::class,
        CommandLoggerMiddleware::class,    // needs LoggerInterface from container
        TransactionMiddleware::class,      // needs Connection from container
        CommandHandlerMiddleware::class,
    ],
    new MiddlewareResolver($container)
);
```

### Re-entrant dispatch (dispatch-within-dispatch)

If a handler dispatches another command via the same bus, `CommandLockerMiddleware` queues the inner message and defers it until the outer handler finishes. This prevents interleaved execution. The final execution order is: outer handler completes, then inner handler runs. Do not rely on the inner command's side effects being visible inside the outer handler.

## Related packages

- [container.md](./container.md) — `Altair\Container\Container` is used by `MiddlewareResolver` to instantiate middleware and by both configuration classes to wire the bus.
- [middleware.md](./middleware.md) — Courier's `CommandMiddlewareInterface` is conceptually parallel to PSR-15 HTTP middleware in `Altair\Middleware\*`. The two pipelines are independent; HTTP middleware handles requests and responses, Courier middleware handles command messages.
- [happen.md](./happen.md) — `Altair\Happen` is the event dispatcher. Courier does not fire events; if you need event dispatch after command execution, do so inside the handler or in a custom middleware.

## Limitations

- **Synchronous only.** There is no transport layer, no queue, no async dispatch. Every `$bus->handle(...)` call blocks until the full pipeline completes.
- **No retry semantics.** Exceptions thrown by handlers propagate up the call stack. There is no retry policy, dead-letter queue, or backoff.
- **No result propagation.** `CommandBusInterface::handle` returns `void`. Handlers communicate outcomes through message annotations (`withLogMessage`) or injected services. If you need a return value, consider a query bus pattern instead.
- **Middleware validation is class-based.** `withMiddlewares` checks that each entry is a subclass of `CommandMiddlewareInterface` using `is_subclass_of` with a class-name string. Passing an object that is already instantiated bypasses this check — use `add` for objects instead.
- **`LogMessageTrait::withLogMessage` mutates `$this`** (tracked in [#47](https://github.com/univeros/framework/issues/47)). The trait assigns `$this->logMessage` and returns `$this` rather than a new instance — inconsistent with the framework's `with*` immutability convention. Until that is reconciled, expect the original message object to carry the log annotation after dispatch.
