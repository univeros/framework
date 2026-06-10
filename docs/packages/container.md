# Container

A runtime, reflection-backed, PSR-11 dependency-injection container with auto-wiring, generic-typed resolution, fluent bindings, attribute autowiring, contextual bindings, tagged services, lazy services, decorators, and isolated child scopes.

**Composer package:** `univeros/container`
**Root namespace:** `Altair\Container`

---

## Introduction

The Container is the wiring backbone of the Altair framework. Every package that builds object graphs (Http, Cache, Filesystem, Happen, Courier, Persistence, Messaging) delegates construction here.

It resolves typed constructor dependencies by reflection (cached), so most classes need no registration at all: ask for a class and you get a fully wired instance. Registration is for the cases reflection cannot infer: binding an interface to an implementation, supplying scalars, sharing singletons, factories, and so on.

Three principles shape the design:

- **Runtime, no build step.** Bindings are registered at runtime and reflection is cached in memory (optionally on disk). There is no compile/warmup phase; the container can be reconfigured between requests.
- **Typed.** `make()`/`get()`/`call()` carry generic `@template` types, so `$container->make(Foo::class)` is statically known to return `Foo`, with no PHPDoc gymnastics at the call site.
- **Fail loud, fail safe.** Unresolvable dependencies throw with the full resolution path (`A -> B -> C`); cycles are detected; and a failed resolution never corrupts the container's internal state.

## Installation

```bash
composer require univeros/container
```

It ships with the framework meta-package; standalone it requires only `psr/container`.

## Quick start

```php
use Altair\Container\Container;

$container = new Container();

// Zero-config autowiring:
$service = $container->make(App\Service\Mailer::class); // typed as Mailer

// Bind an interface to an implementation:
$container->alias(App\Contracts\Clock::class, App\System\SystemClock::class);

// Share a singleton:
$container->singleton(App\Service\Mailer::class);

// A factory for a class the container can't autowire:
$container->factory(App\Db\Connection::class, fn(App\Config $config) => new App\Db\Connection($config->dsn()));

$mailer = $container->get(App\Service\Mailer::class);
```

## Concepts

**Resolution vs. construction.** `get($id)` returns a service, honouring a shared singleton if one exists. `make($class, $params)` always constructs a *fresh* instance (applying call-time parameter overrides). `call($target, $params)` invokes a callable with its parameters autowired. `has($id)` reports whether an id is explicitly registered (bindings, instances, or the container's own self-binding); it does **not** probe every autowireable class.

**Definitions are fluent.** `bind($id)` returns a `Definition` you configure: `->to(Concrete::class)`, `->using($factoryClosure)`, `->withParameters(['name' => $value])`, `->shared()`, `->lazy()`, `->tag('...')`. The sugar methods `singleton()`, `factory()`, `instance()`, `value()` and `alias()` cover the common cases. (Registration methods return a `Definition`, not the container, so configure one binding per fluent chain.)

**Self-binding.** The container resolves `Container`, `Psr\Container\ContainerInterface`, `FactoryInterface` and `InvokerInterface` to itself; a service-locator dependency receives the real container, never a fresh empty one.

**Reflection is cached.** Each class is reflected once into immutable metadata held in an `ArrayReflectionCache` (default) or a cross-request `FileReflectionCache`.

## Usage

### Bindings

```php
$container->bind(Report::class)->withParameters(['format' => 'pdf']); // raw param by name
$container->singleton(Clock::class, SystemClock::class);              // shared, interface->impl
$container->factory(Connection::class, fn(Config $c) => new Connection($c->dsn())); // factory; params autowired
$container->instance(Config::class, $config);                        // pre-made, shared
$container->value('app.locale', 'en-GB');                            // a raw value
$container->alias(LoggerInterface::class, FileLogger::class);        // interface -> implementation
```

`make()` accepts call-time overrides by parameter name:

```php
$report = $container->make(Report::class, ['format' => 'csv']);
```

### Attribute autowiring

Declarative wiring without a Configuration class:

```php
use Altair\Container\Attribute\{Inject, Autowire, Factory, Lazy, Tag};

final class Handler
{
    public function __construct(
        #[Inject(FileLogger::class)] private LoggerInterface $logger,   // resolve a specific id
        #[Autowire(param: 'app.locale')] private string $locale,        // a registered value()
    ) {}
}

#[Factory(WidgetFactory::class)]   // build via WidgetFactory::__invoke()
final class Widget { /* ... */ }

#[Lazy]                            // defer construction until first use
final class HeavyService { /* ... */ }

#[Tag('reporters')]                // collect under a tag
final class SalesReporter implements ReporterInterface { /* ... */ }
```

`#[Autowire(service: X::class)]` resolves a specific service for a parameter.

### Contextual bindings

Give different consumers different implementations of the same dependency:

```php
$container->when(NightlyJob::class)->needs(LoggerInterface::class)->give(FileLogger::class);
$container->when(ApiController::class)->needs(LoggerInterface::class)->giveValue($nullLogger);
```

### Tagged services

```php
$container->bind(SalesReporter::class)->tag('reporters');
$container->bind(StockReporter::class)->tag('reporters');

foreach ($container->tagged('reporters') as $reporter) { /* ... */ }   // resolved lazily
```

### Decorators

`extend()` runs after an id resolves; decorators stack and run in registration order. A decorator that returns an object replaces the instance; returning nothing keeps the original (a side-effect hook).

```php
$container->extend(FormattedResponder::class, fn(FormattedResponder $r) => $r->withFormatter(JsonFormatter::class));
```

### Lazy services

A `->lazy()` binding (or `#[Lazy]`) returns a placeholder that constructs the real instance on first use. On **PHP 8.4+** this uses native lazy objects (`ReflectionClass::newLazyProxy`); on **PHP 8.3** it resolves eagerly; behaviour is identical, only the deferral differs.

### Invoking callables

```php
$container->call(fn(Clock $clock) => $clock->now());          // closure
$container->call([$controller, 'show'], ['id' => 42]);         // [object, method] + overrides
$container->call(JobRunner::class);                            // invokable class (resolved, then __invoke)
$container->call('App\Support\helper');                        // function name / 'Class::method'
```

### Child scopes

```php
$request = $container->createScope();   // inherits parent definitions
$request->instance(RequestContext::class, $context); // scoped: invisible to the parent
```

A child shares the parent's definitions but keeps its own singleton store and may override bindings without mutating the parent.

## Configuration: the `ConfigurationInterface` wiring pattern

Packages register their services through `Altair\Configuration\Contracts\ConfigurationInterface::apply(Container $container)`:

```php
final class MailerConfiguration implements ConfigurationInterface
{
    public function apply(Container $container): void
    {
        $container->singleton(Mailer::class);
        $container->alias(MailerInterface::class, Mailer::class);
        $container->factory(Transport::class, fn(Env $env) => Transport::fromDsn($env->get('MAIL_DSN')));
    }
}
```

## Reflection caching

`Container` defaults to a `CachedReflector` over an in-memory `ArrayReflectionCache`. For a cross-request cache, inject a `FileReflectionCache` (it serializes the extracted metadata, never live `Reflection*` objects, so it round-trips safely):

```php
use Altair\Container\Reflection\CachedReflector;
use Altair\Container\Cache\FileReflectionCache;

$container = new Container(new CachedReflector(cache: new FileReflectionCache('/var/cache/altair')));
```

Implement `Altair\Container\Contracts\ReflectorInterface` or `ReflectionCacheInterface` to plug in your own.

## Testing

```php
$container = new Container();

// Swap an implementation:
$container->alias(MailerInterface::class, FakeMailer::class);

// Inject a pre-built mock:
$container->instance(Clock::class, $frozenClock);

self::assertInstanceOf(FakeMailer::class, $container->get(MailerInterface::class));
```

`make()` returns fresh instances; bind a `singleton()` (resolved via `get()`) when a test needs identity.

## Extending

- **Custom reflector / cache:** implement `ReflectorInterface` / `ReflectionCacheInterface` and pass them to the constructor.
- **Custom resolution:** the container composes a `Resolver`, `ParameterResolver`, `Invoker` and `ResolutionStack` under `Altair\Container\Resolution`, small, single-purpose classes you can study or replace in a fork.

## Exceptions

All extend `Altair\Container\Exception\ContainerException` (which implements PSR-11 `ContainerExceptionInterface`):

- `NotFoundException`: `get()` for an unknown id (PSR-11 `NotFoundExceptionInterface`).
- `AutowireException`: a parameter cannot be satisfied (renders the resolution path).
- `CircularDependencyException`: a dependency cycle (renders the chain).

## Related packages

- [configuration.md](./configuration.md): the `ConfigurationInterface` wiring pattern and env loading.
- [http.md](./http.md), [courier.md](./courier.md), [messaging.md](./messaging.md), [persistence.md](./persistence.md): major consumers that register services through Configurations.
- [introspection.md](./introspection.md): `bin/altair container:inspect` reads the container's definitions and realised singletons.

## Limitations

- **`has()` covers explicit registrations only**: bindings, instances, and the self-binding; not every autowireable class. Don't use it as a "can I make this?" probe.
- **No compile step.** Resolution is reflection-driven at runtime (cached). For extreme hot paths, supply a `FileReflectionCache`; there is deliberately no Symfony-style compiled container.
- **Lazy deferral needs PHP 8.4+.** On 8.3 a lazy binding resolves eagerly (still correct, just not deferred).
- **Unions/intersections** are auto-wired best-effort (each union member is tried; an intersection needs a binding satisfying all members). Ambiguous cases should be bound explicitly.
- **Scopes inherit definitions, not parent singletons' identity for child overrides**: a child resolves inherited shared services from the parent; rebind in the child to scope them locally.
