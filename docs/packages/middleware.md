# Middleware

A generic, domain-agnostic middleware pipeline — a typed Payload flows through an ordered stack of middleware driven by a Runner — entirely distinct from the PSR-15 HTTP middleware pipeline in `univeros/http`.

**Package:** `univeros/middleware`
**Namespace:** `Altair\Middleware`

---

## Introduction

The Middleware package implements the middleware pipeline pattern in its most general form. It makes no assumptions about what you are processing. A `Payload` is a typed envelope carrying arbitrary named attributes. `MiddlewareInterface` is a single `__invoke` method that receives a `Payload` and a `callable $next` that continues the chain. A `Runner` holds a queue of those callables and executes them in order. Nothing in this package knows about HTTP, requests, or responses.

This design is intentional. The same pipeline machinery that handles a sequence of input-sanitation steps can also wrap a command-bus dispatch, a multi-stage validation pass, or a chain of data-transformation rules. Sub-packages that need a sequential, interruptible processing chain depend on `univeros/middleware` and extend `PayloadInterface` to add their own semantic attributes without reimplementing the pipeline.

The `Sanitation` and `Validation` packages both use this package directly: `Altair\Sanitation\Contracts\FilterInterface` extends `MiddlewareInterface`, and `Altair\Validation\Contracts\RuleInterface` does the same. Their runners extend `MiddlewareRunnerInterface`. This is the canonical pattern for building a domain-specific pipeline on top of the generic contracts.

**This package is not a PSR-15 HTTP middleware implementation.** PSR-15 (`Psr\Http\Server\MiddlewareInterface`) processes `ServerRequestInterface` objects and produces `ResponseInterface` objects. The HTTP pipeline for Altair lives in `univeros/http` and is built on `relay/relay` 2.x. If you are building or configuring HTTP request-handling middleware, read [`./http.md`](./http.md) instead. The two contracts are incompatible by design: PSR-15 middleware cannot be dropped into an Altair `Runner`, and an Altair `MiddlewareInterface` cannot satisfy a PSR-15 dispatcher.

The `MiddlewareManager` sits one level above the `Runner`. It wraps a `MiddlewareRunnerInterface` instance and exposes a single `__invoke(PayloadInterface): PayloadInterface` entry point. In a DI-container setup, the Manager is the object you inject into application code; it hides the queue internals and makes the pipeline trivially replaceable by swapping the bound `MiddlewareManagerInterface` alias.

---

## Installation

Install via Composer:

```bash
composer require univeros/middleware
```

The only runtime dependencies are PHP 8.3+ and `univeros/structure` (for the `Queue` collection). If you are consuming the full `univeros/framework` monorepo, the package is already satisfied through the root `replace` map.

---

## Quick start

The following example defines a minimal payload, writes two middleware, builds a pipeline, and executes it.

```php
use Altair\Middleware\Contracts\MiddlewareInterface;
use Altair\Middleware\Contracts\PayloadInterface;
use Altair\Middleware\MiddlewareManager;
use Altair\Middleware\Payload;
use Altair\Middleware\Runner;
use Altair\Structure\Queue;

// Middleware that stamps a "started_at" timestamp on the way in.
class TimestampMiddleware implements MiddlewareInterface
{
    public function __invoke(PayloadInterface $payload, callable $next): PayloadInterface
    {
        $stamped = $payload->withAttribute('started_at', hrtime(true));
        return $next($stamped);
    }
}

// Middleware that appends a log entry after the rest of the chain completes.
class AuditMiddleware implements MiddlewareInterface
{
    public function __invoke(PayloadInterface $payload, callable $next): PayloadInterface
    {
        $result = $next($payload);
        return $result->withAttribute('audit', 'processed');
    }
}

$queue   = new Queue([new TimestampMiddleware(), new AuditMiddleware()]);
$runner  = new Runner($queue);
$manager = new MiddlewareManager($runner);

$output = $manager(new Payload(['input' => 'hello']));

echo $output->getAttribute('audit');       // 'processed'
echo $output->getAttribute('started_at');  // hrtime value
```

---

## Concepts

### Payload

`Payload` is the value that travels through the pipeline. It implements `PayloadInterface`, which defines a named-attribute store: `getAttribute`, `getAttributes`, `withAttribute`, `withAttributes`, and `withoutAttribute`. Every method that returns a modified payload returns a **new instance**; the original is never mutated. `Payload` also implements `JsonSerializable`, serializing its attribute array directly.

The attribute store is deliberately untyped. You put whatever your domain needs into the payload and retrieve it by name on the other side. Sub-packages derive their own `PayloadInterface` that re-declares attributes specific to their domain, while the underlying `Payload` class and the generic contracts remain unchanged.

### Middleware

`MiddlewareInterface` is a single-method contract:

```php
public function __invoke(PayloadInterface $payload, callable $next);
```

The middleware receives the current payload and a callable that represents the rest of the pipeline. It can modify the payload before calling `$next`, inspect or modify the payload returned by `$next`, or skip `$next` entirely to short-circuit. There is no return type annotation on the interface; in practice every middleware in this package returns `PayloadInterface`.

### Runner

`Runner` implements `MiddlewareRunnerInterface`:

```php
public function __invoke(PayloadInterface $payload): PayloadInterface;
```

Internally, `Runner` holds a `Queue` (from `univeros/structure`) and an optional `MiddlewareResolverInterface`. On each call it pops the next entry off the queue and invokes it with `($payload, $this)`. Passing `$this` as `$next` makes the runner itself the continuation, so middleware calls back into the runner to advance the chain. When the queue is empty the runner returns the payload unchanged via a default identity closure.

### MiddlewareManager

`MiddlewareManager` is a thin facade over `MiddlewareRunnerInterface`. Its sole job is to hold a runner and expose the same `__invoke` signature. In a container-wired application you bind `MiddlewareManagerInterface` to `MiddlewareManager` and inject the manager; calling code never sees the queue or the runner directly.

### MiddlewareResolver

`MiddlewareResolverInterface` converts a queue entry — which may be an object, a class name string, or any other value — into a concrete `MiddlewareInterface`. The built-in `MiddlewareResolver` checks whether the entry is already an object; if it is, it returns it as-is. Otherwise it calls `Container::make` to instantiate it via the DI container. You may supply any `callable` instead when you do not have a container.

### Ordering semantics

Middleware execute in **queue order**: the first entry enqueued is the first to run. Because each middleware wraps the continuation, code that runs **before** `$next(...)` executes in FIFO order, and code that runs **after** `$next(...)` executes in LIFO order. Given a queue `[A, B, C]`:

- Before-`$next` code runs: A → B → C
- After-`$next` code runs: C → B → A

The test suite illustrates this concretely: `FakeMiddleware` appends a counter both before and after calling `$next`. Three instances in a queue produce the attribute value `'123456'`, which reflects the inward FIFO pass (1, 2, 3) followed by the outward LIFO pass (4, 5, 6).

---

## Usage

### Defining a Payload

Use the built-in `Payload` class when you do not need domain-specific attribute semantics. Seed it with an initial array or leave it empty.

```php
use Altair\Middleware\Payload;

// Empty payload — attributes populated by middleware.
$payload = new Payload();

// Pre-seeded payload.
$payload = new Payload([
    'user_id' => 42,
    'action'  => 'create_order',
]);
```

To give your pipeline its own typed contract, implement `PayloadInterface` directly:

```php
use Altair\Middleware\Contracts\PayloadInterface as BasePayloadInterface;

interface OrderPayloadInterface extends BasePayloadInterface
{
    public function getOrderId(): int;
}
```

Your concrete class should use `clone` to return a new instance from every `with*` method, matching the immutability contract enforced by the base interface docblocks.

### Writing a Middleware

Every middleware is an invokable class implementing `MiddlewareInterface`. The method signature must accept `PayloadInterface $payload` and `callable $next` and should return `PayloadInterface`.

```php
use Altair\Middleware\Contracts\MiddlewareInterface;
use Altair\Middleware\Contracts\PayloadInterface;

class LoggingMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly \Psr\Log\LoggerInterface $logger)
    {
    }

    public function __invoke(PayloadInterface $payload, callable $next): PayloadInterface
    {
        $this->logger->info('Pipeline entry', $payload->getAttributes());

        $result = $next($payload);

        $this->logger->info('Pipeline exit', $result->getAttributes());

        return $result;
    }
}
```

### Building a Runner / pipeline

Instantiate a `Queue` with your middleware in execution order, then wrap it in a `Runner`. If your middleware are already instantiated objects, no resolver is needed.

```php
use Altair\Middleware\Runner;
use Altair\Structure\Queue;

$queue  = new Queue([
    new LoggingMiddleware($logger),
    new ValidationMiddleware($rules),
    new HandlerMiddleware($handler),
]);
$runner = new Runner($queue);

$output = $runner(new Payload(['data' => $input]));
```

To store class names instead of objects — for example, to defer instantiation — pass a resolver as the second argument to `Runner`. A plain closure works when you do not have a container:

```php
$queue  = new Queue([
    LoggingMiddleware::class,
    ValidationMiddleware::class,
    HandlerMiddleware::class,
]);
$runner = new Runner($queue, fn(string $class): object => new $class());
```

### The MiddlewareManager

Wrap the runner in a `MiddlewareManager` when you want to expose a single entry point to calling code. The manager delegates the invocation to the runner and returns the resulting payload.

```php
use Altair\Middleware\MiddlewareManager;

$manager = new MiddlewareManager($runner);
$output  = $manager(new Payload(['data' => $input]));
```

In a container-wired application, register the bindings via `MiddlewareConfiguration`:

```php
use Altair\Middleware\Configuration\MiddlewareConfiguration;

$configuration = new MiddlewareConfiguration();
$configuration->apply($container);

// The container now resolves MiddlewareManagerInterface to MiddlewareManager,
// MiddlewareRunnerInterface to Runner, and MiddlewareResolverInterface to MiddlewareResolver.
// You must bind Queue and its middleware entries separately.
```

`MiddlewareConfiguration` wires the resolver with `Container::make` support, so class-name strings in the queue are automatically resolved through the DI container.

### Short-circuiting / early termination

To stop pipeline execution before all middleware have run, return a payload without calling `$next`. Downstream middleware are skipped; the caller receives the payload at the point of interruption.

```php
class AuthorizationMiddleware implements MiddlewareInterface
{
    public function __invoke(PayloadInterface $payload, callable $next): PayloadInterface
    {
        if (!$this->isAuthorized($payload->getAttribute('user_id'))) {
            // Return immediately — no further middleware run.
            return $payload->withAttribute('error', 'unauthorized');
        }

        return $next($payload);
    }
}
```

Callers should check for error attributes on the returned payload when early termination is a possible outcome.

### Error handling

`MiddlewareInterface` does not define an error contract. The recommended approach is to add a dedicated error-catching middleware at the front of the queue (so it wraps all others) that catches exceptions and records them on the payload.

```php
class ErrorCatchingMiddleware implements MiddlewareInterface
{
    public function __invoke(PayloadInterface $payload, callable $next): PayloadInterface
    {
        try {
            return $next($payload);
        } catch (\Throwable $e) {
            return $payload
                ->withAttribute('error', $e->getMessage())
                ->withAttribute('exception', $e);
        }
    }
}
```

Placing this middleware first in the queue ensures it catches exceptions thrown by any subsequent middleware in the chain.

---

## Configuration

This package has no configuration file of its own. The `MiddlewareConfiguration` class registers DI-container bindings for the three interface-to-class aliases and injects the container into `MiddlewareResolver`. You wire the `Queue` and populate it with middleware entries separately, outside of `MiddlewareConfiguration`, because the queue contents are application-specific.

If you are not using the Altair DI container (`univeros/container`), assemble `Queue`, `Runner`, and `MiddlewareManager` manually as shown in the usage section above.

---

## Testing

### Testing a middleware in isolation

Because `MiddlewareInterface` is a plain invokable, you can test a single middleware by constructing a minimal `$next` callable and asserting on the returned payload.

```php
use Altair\Middleware\Payload;
use PHPUnit\Framework\TestCase;

class LoggingMiddlewareTest extends TestCase
{
    public function testLogsEntryAndExit(): void
    {
        $logger     = $this->createMock(\Psr\Log\LoggerInterface::class);
        $middleware = new LoggingMiddleware($logger);
        $payload    = new Payload(['user_id' => 1]);

        // A $next callable that returns the payload unchanged.
        $next = fn(PayloadInterface $p): PayloadInterface => $p;

        $logger->expects($this->exactly(2))->method('info');

        $result = $middleware($payload, $next);

        $this->assertSame(1, $result->getAttribute('user_id'));
    }
}
```

The identity `$next` closure is the standard test double for the continuation. To simulate a downstream middleware modifying the payload, return a modified payload from the closure:

```php
$next = fn(PayloadInterface $p): PayloadInterface => $p->withAttribute('processed', true);
```

### Testing the full pipeline

Use the real `Queue`, `Runner`, and `MiddlewareManager` classes in integration tests. The suite in `tests/Middleware/` does exactly this — no mocks for the pipeline infrastructure itself.

```php
use Altair\Middleware\MiddlewareManager;
use Altair\Middleware\Payload;
use Altair\Middleware\Runner;
use Altair\Structure\Queue;
use PHPUnit\Framework\TestCase;

class PipelineTest extends TestCase
{
    public function testMiddlewareRunInOrder(): void
    {
        $queue   = new Queue([
            new TimestampMiddleware(),
            new AuditMiddleware(),
        ]);
        $manager = new MiddlewareManager(new Runner($queue));
        $output  = $manager(new Payload(['input' => 'hello']));

        $this->assertNotNull($output->getAttribute('started_at'));
        $this->assertSame('processed', $output->getAttribute('audit'));
    }
}
```

Verify immutability by asserting that the input payload is not the same object as the output:

```php
$input  = new Payload(['x' => 1]);
$output = $manager($input);

$this->assertNotSame($input, $output);
$this->assertNull($input->getAttribute('audit'));
```

Test files for this package live under `tests/Middleware/` and mirror the `src/Altair/Middleware/` layout. Use PHPUnit 11 attribute style (`#[Test]`, `#[DataProvider]`).

---

## Extending

The middleware layer itself is the extension point. You do not extend `Runner`, `Payload`, or `MiddlewareManager` to add domain behavior — you write new middleware and add them to the queue.

To create a domain-specific pipeline on top of this package, follow the pattern used by `Sanitation` and `Validation`:

1. Extend `PayloadInterface` with domain-specific attribute accessors.
2. Implement a concrete payload class using `clone` in all `with*` methods.
3. Extend `MiddlewareInterface` with a method name meaningful to your domain (e.g. `handle`, `process`, `filter`). The generic `__invoke` signature of the base interface remains the actual contract the Runner calls.
4. Extend `MiddlewareRunnerInterface` for type-narrowing if needed.
5. Optionally extend `MiddlewareResolverInterface` if your middleware entries require custom resolution logic.

---

## Recipes

### Logging middleware

Attach a PSR-3 logger to record what enters and exits the pipeline without modifying the payload. Place this first in the queue so it wraps every other middleware.

```php
class PipelineLogger implements MiddlewareInterface
{
    public function __construct(private readonly \Psr\Log\LoggerInterface $logger)
    {
    }

    public function __invoke(PayloadInterface $payload, callable $next): PayloadInterface
    {
        $this->logger->debug('Pipeline start', ['attributes' => array_keys($payload->getAttributes())]);

        $result = $next($payload);

        $this->logger->debug('Pipeline end', ['attributes' => array_keys($result->getAttributes())]);

        return $result;
    }
}
```

### Validation middleware

Validate a required attribute before passing the payload deeper. Return early with an error attribute when validation fails so downstream middleware do not see invalid data.

```php
class RequiredFieldMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly string $field)
    {
    }

    public function __invoke(PayloadInterface $payload, callable $next): PayloadInterface
    {
        if ($payload->getAttribute($this->field) === null) {
            return $payload->withAttribute('validation_error', "Missing required field: {$this->field}");
        }

        return $next($payload);
    }
}

// Usage: require 'user_id' before the rest of the chain runs.
$queue = new Queue([
    new RequiredFieldMiddleware('user_id'),
    new HandlerMiddleware($handler),
]);
```

### Transaction wrapper

Wrap downstream middleware in a database transaction using the before/after pattern. The transaction commits on success and rolls back if any downstream middleware throws.

```php
class TransactionMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly \PDO $pdo)
    {
    }

    public function __invoke(PayloadInterface $payload, callable $next): PayloadInterface
    {
        $this->pdo->beginTransaction();

        try {
            $result = $next($payload);
            $this->pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
```

### Retry middleware

Automatically retry the remainder of the pipeline on transient failures. Pass a maximum attempt count and the exception class that is considered retryable.

```php
class RetryMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly int $maxAttempts,
        private readonly string $retryableException,
    ) {
    }

    public function __invoke(PayloadInterface $payload, callable $next): PayloadInterface
    {
        $attempts = 0;

        retry:
        try {
            return $next($payload);
        } catch (\Throwable $e) {
            if (!($e instanceof $this->retryableException) || ++$attempts >= $this->maxAttempts) {
                throw $e;
            }
            goto retry;
        }
    }
}
```

### Attribute enrichment middleware

Fetch supplementary data and attach it to the payload before the business-logic middleware run. This keeps the business middleware free of I/O concerns.

```php
class UserEnrichmentMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly UserRepository $users)
    {
    }

    public function __invoke(PayloadInterface $payload, callable $next): PayloadInterface
    {
        $userId = $payload->getAttribute('user_id');
        $user   = $userId !== null ? $this->users->findById($userId) : null;

        return $next($payload->withAttribute('user', $user));
    }
}
```

---

## Related packages

- [`./http.md`](./http.md) — The HTTP package implements a PSR-15 pipeline (`Psr\Http\Server\MiddlewareInterface`) using `relay/relay` 2.x. Its middleware receive `ServerRequestInterface` and return `ResponseInterface`. It is entirely separate from this package and the two pipelines are not interchangeable.
- [`./courier.md`](./courier.md) — The Courier package implements a command-bus pattern. Its `CommandMiddlewareInterface` follows the same shape (`handle(CommandMessageInterface, callable $next)`) and conceptual model as `MiddlewareInterface`, though it does not depend on `univeros/middleware` directly.

---

## Limitations

- **Synchronous, single-threaded execution.** The pipeline runs synchronously on the PHP call stack. There is no support for async middleware, generators, or Promises. Each middleware must return before the next can proceed.
- **No built-in error attribute convention.** The package does not define a standard key for signaling errors on the payload. Choose a convention for your application (for example, `'error'` or `'errors'`) and document it so all middleware in the pipeline agree.
- **Queue is consumed on each run.** `Runner` calls `Queue::pop` to advance the pipeline. A single `Runner` instance cannot be reused for a second pipeline execution because the queue will be empty after the first run. Construct a new `Runner` (and a new `Queue`) for each execution.
- **`Payload::withoutAttribute` throws at runtime** (tracked in [#41](https://github.com/univeros/framework/issues/41)). The implementation calls `unset($cloned[$name])` on a `Payload` that does not implement `ArrayAccess`. On PHP 8+ this raises `Error: Cannot use object of type Altair\Middleware\Payload as array`. Until the source is fixed, work around it by rebuilding the payload via `withAttributes($payload->getAttributes())` with the unwanted key already excluded from the input array.
