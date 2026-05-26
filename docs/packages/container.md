# Container

A reflection-backed, PSR-11 dependency injection container with auto-wiring, singleton scoping, interface aliasing, post-instantiation hooks, and first-class callable invocation.

**Composer package:** `univeros/container`
**Root namespace:** `Altair\Container`

---

## Introduction

The Container package is the wiring backbone of the Altair framework. Every other package that needs to build object graphs — Http, Cache, Filesystem, Happen, Courier, and the rest — delegates that construction here. Understanding the container is the prerequisite to understanding how any of those packages bootstrap themselves.

At its core the container is an auto-wiring injector. You hand it a class name and it reads that class's constructor via PHP's `\ReflectionClass` API, resolves each typed parameter by recursively making its dependencies, and returns a fully provisioned instance — without you having to describe a single binding. This reflection pass happens once per class per request; the results are cached in an in-memory `ArrayCache` (or an opcode-friendly `FileCache` you can supply instead) so the cost is paid only the first time.

The container implements `Psr\Container\ContainerInterface` (`psr/container ^2`), so it is a drop-in wherever a PSR-11 container is expected. Its `get(string $id): mixed` method delegates to `make()`, and `has(string $id): bool` returns true if the identifier is known through an alias, a delegate, or a prior `share()` call. Note that auto-wirable concrete classes that have never been mentioned explicitly return `false` from `has()` — the container discovers them lazily through reflection when you call `make()`.

The design deliberately differs from Symfony's compiled container and Laravel's IoC container in one key regard: there is no build or warmup step. Bindings are registered at runtime, reflection is cached in memory, and the container can be reconfigured between requests if your application needs that flexibility. The trade-off is that the container is more explicit about what it knows: `has()` only covers explicitly registered identifiers, not every auto-wirable class in the codebase.

The framework's own configuration layer (`univeros/configuration`) standardises how packages register bindings: each package ships one or more classes that implement `Altair\Configuration\Contracts\ConfigurationInterface`, whose single method `apply(Container $container): void` adds aliases, delegates, and shares to a container instance passed from the application bootstrap. This convention lets you compose your container's wiring from independent units without a monolithic service-provider file.

---

## Installation

```bash
composer require univeros/container
```

The package has no extension requirements beyond PHP 8.3 and depends only on `psr/container ^2` and `univeros/structure ^2` (for the `Map`-based collection primitives used internally).

---

## Quick start

The following example shows the three most common operations: instantiating the container, registering an interface-to-implementation alias, and resolving a concrete class that depends on that interface.

```php
<?php declare(strict_types=1);

use Altair\Container\Container;
use Altair\Container\Definition;

// 1. Create the container. CachedReflection is wired up automatically.
$container = new Container();

// 2. Tell the container which concrete class satisfies DepInterface.
$container->alias(DepInterface::class, DepImplementation::class);

// 3. Resolve a class whose constructor type-hints DepInterface.
//    The container reflects RequiresInterface, sees the DepInterface
//    parameter, follows the alias, and injects a fresh DepImplementation.
$object = $container->make(RequiresInterface::class);

// 4. Pass scalar constructor arguments as raw values via a Definition.
$object = $container->make(
    SimpleNoTypehintClass::class,
    (new Definition([]))->addRaw('arg', 'hello')
);

// 5. Execute a callable with its dependencies auto-resolved.
$result = $container->execute(
    [SomeService::class, 'handleRequest'],
    (new Definition([]))->addRaw('extraParam', 42)
);
```

---

## Concepts

### Resolution model

Every call to `make(string $name)` passes through several ordered stages:

1. **Alias resolution** — the name is normalised to lowercase with no leading backslash, then checked against the aliases registry. If an alias exists the concrete class name is substituted before any further work.
2. **Cycle detection** — the container tracks which classes are currently being built in a `$making` array. If the same normalised name appears twice in a single call stack, an `InjectionException` is thrown immediately rather than entering an infinite recursion.
3. **Shares check** — if the class is registered as shared and a stored instance already exists, that instance is returned immediately. No reflection, no delegates, no prepares.
4. **Delegate dispatch** — if a delegate factory is registered for the class, that factory is invoked (with its own dependencies auto-resolved) and its return value becomes the instance.
5. **Auto-wire provisioning** — the reflector is asked for the constructor and its parameters. Each typed parameter is resolved by calling `make()` recursively. Untyped parameters fall through to global parameter definitions, then to declared default values. Missing required parameters without a definition or default produce an `InjectionException`.
6. **Post-instantiation prepares** — every registered prepare callable for the class name and for each interface the object implements is executed in turn.
7. **Share storage** — if the class was registered as shared (even before the first instance existed), the fresh instance is stored for all subsequent `make()` calls.

### Definitions

A `Definition` is a value object that carries per-instantiation argument overrides. Three key prefix conventions control how arguments are interpreted:

| Prefix | Method | Meaning |
|--------|--------|---------|
| `:` (colon) | `addRaw($param, $value)` | Inject the value as-is — no container lookup |
| `+` (plus) | `addDelegate($param, $callable)` | Invoke the callable to produce the argument value |
| `@` (at) | (key prefix for nested class definitions) | Inline class + args pair for that parameter |
| (no prefix) | `add($param, $className)` | Resolve the named class via the container |

Definitions compose: a call-time `Definition` passed to `make()` takes precedence over a class-level definition registered with `define()`. The merge uses `array_replace`, so call-time keys win and absent keys fall through to the pre-registered definition.

### Sharing (singleton scoping)

Calling `share($nameOrInstance)` registers a class name or existing object as a singleton within this container instance. Once the first instance is produced it is stored; every subsequent `make()` for that name returns the same object reference without invoking reflection or prepares again.

### Aliasing

`alias(string $original, string $alias)` maps one name to another — typically an interface or abstract class to a concrete implementation. The alias is resolved transparently before any construction logic runs. You can also alias arbitrary string identifiers, not just PHP class names; the test suite demonstrates `$container->alias('custom', DepImplementation::class)`.

One important constraint: you cannot alias a class that is already shared (the `SharesCollection` enforces this), and you cannot share a class that is already aliased. The two mechanisms are mutually exclusive per-name.

### Prepares (post-instantiation hooks)

A prepare callable is executed after every fresh instantiation of a given class or interface. The callable receives the new object and the container as its two arguments. Prepares are useful for setter injection, mixin application, or any mutation that cannot be expressed in a constructor. The prepare runs against both the direct class name and all interfaces the object implements, so a single `prepare(SomeInterface::class, $callable)` will fire for every object that implements `SomeInterface`.

### Delegates (factory fallback)

A delegate is a factory callable registered for a specific class name. When the container would otherwise try to auto-wire that class, it invokes the delegate instead. The delegate's own parameters are auto-resolved, so it participates fully in the injection chain. Delegates are how packages like Cache and Http register lazy construction of external clients (Redis connections, route dispatchers) without needing those clients to have conventional PHP constructors.

### Executable invocation

`execute($callableOrMethodString, ?Definition $definition)` invokes any callable with its dependencies auto-resolved. The container understands the following callable forms:

- PHP closures and arrow functions
- Named global functions (`'Altair\Tests\Container\testExecuteFunction'`)
- `'ClassName::methodName'` strings — the class is made by the container first
- `'ClassName::parent::methodName'` strings — relative static dispatch
- `[ClassName::class, 'method']` arrays
- `[$object, 'method']` arrays with pre-built objects
- Invokable class name strings — the class is made and `__invoke` called
- Invokable object instances

### Reflection caching

Every reflection lookup goes through `CachedReflection`, which wraps a `StandardReflection` with a `ReflectionCacheInterface` store. By default that store is an `ArrayCache` (a plain PHP array, in-process, cleared at end-of-request). If you want to survive across requests you can pass a `FileCache` instance instead; it serialises reflection objects to PHP files in a configurable directory and benefits from OPcache on subsequent hits.

---

## Usage

### `define()` and `make()` — basic instantiation with overrides

Register construction arguments in advance when a class has untyped or ambiguous constructor parameters. You do this because auto-wiring can only follow type-hints; scalar values must be declared explicitly.

```php
use Altair\Container\Container;
use Altair\Container\Definition;

$container = new Container();

// Register raw scalar arguments for InjectorTestRawCtorParams.
$container->define(
    InjectorTestRawCtorParams::class,
    new Definition([
        ':string' => 'hello',
        ':int'    => 42,
        ':float'  => 9.3,
        ':bool'   => true,
        ':null'   => null,
        ':obj'    => new \stdClass(),
        ':array'  => [],
    ])
);

$obj = $container->make(InjectorTestRawCtorParams::class);
```

Pass a `Definition` directly to `make()` to override or supplement the pre-registered definition for a single call. Call-time arguments win over pre-registered ones.

```php
// Pre-registered: arg1 = 'First argument', arg2 = 'Second argument'
$container->define(InjectorTestChildClass::class, $definition);

// Override only arg1 at call time; arg2 still comes from the pre-registered definition.
$obj = $container->make(
    InjectorTestChildClass::class,
    new Definition([':arg1' => 'Override'])
);
// $obj->arg1 === 'Override', $obj->arg2 === 'Second argument'
```

### `defineParameter()` — global typeless parameter defaults

Use `defineParameter()` when a scalar constructor parameter of the same name appears across multiple classes and you want a single point of configuration rather than per-class definitions.

```php
// All classes with a constructor parameter named $thumbnailSize will receive 128
// unless overridden by a class-level or call-time definition.
$container->defineParameter('thumbnailSize', 128);

$service = $container->make(RequiresDependencyWithTypelessParameters::class);
// $service->dependency->thumbnailSize === 128
```

Global parameter definitions are only used when no type-hint, class-level definition, or call-time definition supplies a value for that parameter position.

### `share()` — singleton scoping

Register a class name as shared before any instance is created, or pass an already-constructed object to freeze it as the singleton immediately.

```php
$container->share(RequiresInterface::class);

$a = $container->make(RequiresInterface::class);
$b = $container->make(RequiresInterface::class);
// $a === $b — same object reference
```

You can also share an existing instance. This is useful when integrating the container with objects built outside it (a framework kernel, a database connection obtained from the environment, etc.).

```php
$connection = new DatabaseConnection($dsn);
$container->share($connection); // normalized to DatabaseConnection
```

The container enforces that you cannot call `share()` on a name already registered with `alias()`, and vice versa. The `AliasesCollection` and `SharesCollection` guard this invariant and throw `InvalidArgumentException` if you violate it.

### `alias()` — interface to implementation

Map an interface, abstract class, or arbitrary string identifier to a concrete class name. The alias is resolved transparently every time `make()` is called with the original name.

```php
$container->alias(DepInterface::class, DepImplementation::class);

// RequiresInterface has a constructor parameter typed DepInterface.
// The container follows the alias and injects DepImplementation.
$obj = $container->make(RequiresInterface::class);
```

Aliases can chain: if `DepImplementation` is itself aliased, the chain is followed. The resolution stops at the first name that has no further alias registered.

You can alias arbitrary strings, not just FQCNs:

```php
$container->alias('cache.primary', RedisCacheItemStorage::class);
$cache = $container->make('cache.primary');
```

### `prepare()` — post-resolution mutation hook

Register a callable that runs immediately after every instantiation of a given class or interface. The callable receives `($object, $container)` as arguments and its return value replaces the instance if it is an instance of the registered class.

```php
// Mutate every \stdClass after it is built.
$container->prepare(\stdClass::class, function (\stdClass $obj, Container $container): void {
    $obj->testval = 42;
});

$obj = $container->make(\stdClass::class);
// $obj->testval === 42
```

Registering a prepare against an interface applies it to every object that implements that interface, regardless of how many concrete classes are involved.

```php
$container->prepare(SomeInterface::class, function ($obj, Container $container): void {
    $obj->testProp = 42;
});

// PreparesImplementationTest implements SomeInterface.
$obj = $container->make(PreparesImplementationTest::class);
// $obj->testProp === 42
```

Prepares are indexed by normalised class or interface name. Only one prepare callable can be registered per name; a second `prepare()` call for the same name replaces the first.

### `delegate()` — factory-based construction

Hand off construction of a class to a callable factory. The factory's parameters are themselves auto-resolved. The returned object is used as-is (prepares still run on it).

```php
// Closure factory — receives no injected args here, but the container resolves any typed params it has.
$container->delegate(\stdClass::class, function (): \stdClass {
    $obj = new \stdClass();
    $obj->test = 42;
    return $obj;
});

$obj = $container->make(\stdClass::class);
// $obj->test === 42
```

You can also pass a class name string that has an `__invoke` method. The container will make the factory class first (auto-wiring its own dependencies), then call `__invoke`.

```php
$container->delegate(\stdClass::class, StringStdClassDelegateMock::class);
```

Delegates are commonly combined with aliases. A real-world example from the Cache package:

```php
// In RedisCacheItemStorageConfiguration::apply():
$container
    ->delegate(PredisCacheItemStorage::class, $factory)
    ->alias(CacheItemStorageInterface::class, PredisCacheItemStorage::class);
```

When `make(CacheItemStorageInterface::class)` is called, the alias resolves it to `PredisCacheItemStorage`, the delegate factory fires to build the connection-aware storage, and the result is returned.

### `execute()` — invoke callables with DI

Call any callable and have its parameters auto-resolved, with optional call-time overrides via a `Definition`.

```php
// Named function — auto-resolves ConcreteClass1 parameter from type-hint.
$result = $container->execute('Altair\Tests\Container\testExecuteFunctionWithArg');

// [Class, method] array — container makes the class first.
$result = $container->execute([ExecuteClassDeps::class, 'execute']);

// ['Class', 'parent::method'] — relative static dispatch.
$result = $container->execute(
    [ExecuteClassRelativeStaticMethod::class, 'parent::execute']
);

// 'Class::method' string shorthand.
$result = $container->execute(ExecuteClassStaticMethod::class . '::execute');

// Invokable class name — made by the container, then __invoke called.
$result = $container->execute(ExecuteClassInvokable::class);

// Closure with a raw argument override.
$result = $container->execute(
    [ExecuteClassDepsWithMethodDeps::class, 'execute'],
    (new Definition([]))->addRaw('arg', 9382)
);
// $result === 9382
```

### Reflection caching — where it lives and how to replace it

The default reflector is `CachedReflection`, wired in the `Container` constructor when no `ReflectionInterface` is injected. `CachedReflection` wraps a `StandardReflection` delegate and an `ArrayCache` store.

To use file-based caching backed by OPcache, inject a `FileCache` at container creation time:

```php
use Altair\Container\Cache\FileCache;
use Altair\Container\Reflection\CachedReflection;
use Altair\Container\Reflection\StandardReflection;

$cache = new FileCache('/var/cache/reflection'); // directory must be writable
$reflector = new CachedReflection(new StandardReflection(), $cache);
$container = new Container($reflector);
```

`FileCache` uses `var_export` + `require` so OPcache treats each cached file as compiled bytecode on the second hit. The comment in the source references a Graphiq benchmark showing 500x better throughput than Redis/Memcache for this workload.

To substitute a completely different reflector, implement `Altair\Container\Contracts\ReflectionInterface` and pass it as the first constructor argument. You must implement `getClass`, `getConstructor`, `getConstructorParameters`, `getParameterTypeHint`, `getFunction`, and `getMethod`.

---

## Configuration — the `ConfigurationInterface` wiring pattern

The container itself ships no `Configuration/` subdirectory. The wiring convention lives in `univeros/configuration`, specifically `Altair\Configuration\Contracts\ConfigurationInterface`:

```php
interface ConfigurationInterface
{
    public function apply(Container $container): void;
}
```

Every package in the framework that needs to register bindings ships one or more classes implementing this interface. The `apply` method receives the shared container and calls `alias`, `share`, `delegate`, `define`, or `defineParameter` as needed. Application bootstrap code collects these configuration objects and calls `apply` on each:

```php
use Altair\Configuration\Collection\ConfigurationCollection;

$configs = new ConfigurationCollection();
$configs->add(new RedisCacheItemStorageConfiguration());
$configs->add(new FastRouteConfiguration($routeCollection));
$configs->add(new RelayConfiguration());
$configs->apply($container);
```

`ConfigurationCollection` is itself a `ConfigurationInterface` — it iterates its members and calls `apply` on each. Individual members may be class name strings; in that case `ConfigurationCollection` calls `$container->make($configuration)` first, so configuration classes can themselves receive injected dependencies.

Packages you are likely to interact with through this pattern:

| Package | Example configuration class |
|---|---|
| Cache | `Altair\Cache\Configuration\RedisCacheItemStorageConfiguration` |
| Filesystem | `Altair\Filesystem\Configuration\LocalAdapterConfiguration` |
| Http | `Altair\Http\Configuration\FastRouteConfiguration` |
| Http | `Altair\Http\Configuration\RelayConfiguration` |
| Courier | `Altair\Courier\Configuration\ExecCommandBusConfiguration` |

---

## Testing

### Swapping implementations via `alias()`

The recommended approach for substituting fakes or mocks in tests is the same alias mechanism you use in production. Because `alias()` is called before any `make()`, the substitution is transparent to all code that depends on the interface.

```php
$container = new Container();
$container->alias(DepInterface::class, FakeDepImplementation::class);

$sut = $container->make(SystemUnderTest::class);
// SystemUnderTest receives FakeDepImplementation wherever it type-hints DepInterface
```

### Sharing pre-built mock objects

When you need a PHPUnit mock object injected, share it as an instance before resolving the class under test:

```php
$mock = $this->createMock(CacheItemStorageInterface::class);
$mock->expects($this->once())->method('getItem')->willReturn($cacheItem);

$container = new Container();
$container->share($mock); // shares as CacheItemStorageInterface's concrete mock class
$container->alias(CacheItemStorageInterface::class, $mock::class);

$service = $container->make(ServiceThatUsesCache::class);
```

Alternatively, use `delegate()` to return the mock from a factory:

```php
$container->delegate(CacheItemStorageInterface::class, fn() => $mock);
```

### Using the test fixtures

`tests/Container/fixtures.php` contains a large catalogue of fixture classes that cover every binding pattern. Refer to it when writing new container-related tests; `ContainerTest` exercises each fixture and serves as the canonical example of expected behaviour.

---

## Extending

### Custom `ReflectionInterface` implementations

If you work in a context where PHP's built-in `ReflectionClass` is expensive or unavailable — for example a compilation pipeline or a heavily cached production environment — you can replace the entire reflector stack by implementing `Altair\Container\Contracts\ReflectionInterface`.

Your implementation must handle six methods:

- `getClass(string $class): ReflectionClass`
- `getConstructor(string $class): ?ReflectionMethod`
- `getConstructorParameters(string $class): ?array` — returns `ReflectionParameter[]|null`
- `getParameterTypeHint(ReflectionFunctionAbstract $fn, ReflectionParameter $p): ?string`
- `getFunction(mixed $name): ReflectionFunction`
- `getMethod(mixed $classNameOrInstance, string $methodName): ReflectionMethod`

The simplest path is to extend `StandardReflection` and override only the methods you need.

### Custom `ReflectionCacheInterface` implementations

`CachedReflection` accepts any `ReflectionCacheInterface` store. Implement `get(string $key)` (returning `false` on cache miss) and `put(string $key, mixed $data): ReflectionCacheInterface`. The key prefixes used internally are declared as constants on the interface:

```
ReflectionCacheInterface::CLASSES_KEY_PREFIX            // 'class.'
ReflectionCacheInterface::CONSTRUCTORS_KEY_PREFIX       // 'const.'
ReflectionCacheInterface::CONSTRUCTOR_PARAMETERS_KEY_PREFIX // 'const-params.'
ReflectionCacheInterface::FUNCTIONS_KEY_PREFIX          // 'func.'
ReflectionCacheInterface::METHODS_KEY_PREFIX            // 'method.'
```

---

## Recipes

### 1. Registering a service with scalar dependencies

Use `define()` when a service constructor mixes typed dependencies (auto-wired) with raw scalars (not auto-wirable).

```php
$container->define(
    Mailer::class,
    (new Definition([]))->addRaw('dsn', 'smtp://localhost:25')
);

// The Mailer constructor also type-hints LoggerInterface.
// The container auto-wires LoggerInterface from the alias registered elsewhere.
$mailer = $container->make(Mailer::class);
```

### 2. Swapping an implementation for tests

```php
// Production bootstrap:
$container->alias(LoggerInterface::class, FileLogger::class);

// Test setUp():
$container = new Container();
$container->alias(LoggerInterface::class, NullLogger::class);
// All classes that depend on LoggerInterface now receive NullLogger.
```

### 3. Lazy / factory bindings

Use `delegate()` when construction is expensive or requires runtime data not available at container-build time.

```php
$container->delegate(
    DatabaseConnection::class,
    function (): DatabaseConnection {
        $dsn = $_ENV['DATABASE_URL'] ?? throw new \RuntimeException('DATABASE_URL not set');
        return new DatabaseConnection($dsn);
    }
);

// No connection is opened until the first make() call.
$db = $container->make(DatabaseConnection::class);
```

### 4. Decorating via `prepare()`

Apply a decorator or configure a service after it is built without changing its constructor.

```php
$container->prepare(
    LoggerInterface::class,
    function (LoggerInterface $logger, Container $container): LoggerInterface {
        // Wrap every logger in a request-scoped prefix decorator.
        return new PrefixLogger($logger, '[request-' . uniqid() . ']');
    }
);
```

The prepare return value replaces the original instance when it is an `instanceof` the registered class. If the callable returns `void` (or returns a value that is not an instance of the class), the original object is retained and any mutations made inside the callable are still visible.

### 5. Parent-child container setup via cloning

The container exposes a `__clone()` method that resets the in-progress `$making` tracker. All registered aliases, shares, delegates, definitions, and prepares are shallow-copied via the default PHP clone semantics. This gives you a child container that starts from the same wiring as the parent but can accumulate additional registrations — or trigger a fresh share cycle — without affecting the parent.

```php
$parent = new Container();
$parent->alias(LoggerInterface::class, FileLogger::class);
$parent->share(FileLogger::class);

$child = clone $parent;
$child->alias(LoggerInterface::class, NullLogger::class); // override in child only
```

---

## Related packages

The container is a dependency of virtually every other Altair package. The most commonly referenced:

- [configuration.md](./configuration.md) — the `ConfigurationInterface` / `ConfigurationCollection` pattern that all packages use to register their bindings
- [happen.md](./happen.md) — the event dispatcher; its configuration registers the dispatcher and listener provider with the container
- [http.md](./http.md) — PSR-7/15 stack; `ContainerResolver` uses the container to resolve middleware class names from the Relay pipeline
- [cache.md](./cache.md) — PSR-6/16; all storage backends registered via `delegate()` and `alias()` in configuration classes
- [filesystem.md](./filesystem.md) — Flysystem v3 adapters; each adapter configuration delegates construction to an inline factory
- [courier.md](./courier.md) — command bus; handler registration uses `alias()` and `define()`

---

## Migration notes

No breaking changes were introduced to the Container package during the 2026-05 modernisation sweep (Phase 1). The public API of `Container` — `define`, `defineParameter`, `alias`, `share`, `prepare`, `delegate`, `make`, `execute`, `has`, `get` — is unchanged from prior versions. The internal collection classes now extend `Altair\Structure\Map` (from `univeros/structure ^2`) rather than the older v1 API, but this is invisible to callers.

If you are migrating from a very old revision of the framework (pre-2024), verify that any code passing a `Relay\ResolverInterface` as a delegate has been updated. That interface was removed in the Http package's Phase 3 migration; the resolver is now a plain callable.

---

## Limitations

- **Union types and intersection types are not auto-wired.** `getParameterTypeHint` in `StandardReflection` calls `$parameter->getType()` and returns `null` if the result is not a `ReflectionNamedType` or is a built-in type. A parameter typed `LoggerInterface|NullLogger` resolves to `null` from the reflector's perspective and falls through to a global parameter definition or default — it is never auto-wired. Register an explicit definition or use `defineParameter()` for these parameters.

- **Variadic parameters are not resolved.** The `ArgumentsBuilder` iterates `getParameters()` positionally. A variadic (`...$deps`) last parameter receives no value from the container; it will be empty unless a call-time `Definition` supplies a positional entry for it.

- **Non-public constructors throw.** `provisionInstance` checks `$constructor->isPublic()` and throws `InjectionException` if the constructor is protected or private. Classes relying on named constructors (static factory methods) must use `delegate()` to delegate their construction to that factory.

- **Cyclic dependencies throw immediately.** The `$making` guard detects direct and transitive cycles (A→B→A, A→B→C→A) and throws `InjectionException('Cyclic dependency detected')`. There is no lazy-proxy mechanism to break cycles; you must refactor the dependency graph.

- **`has()` only covers explicitly registered identifiers.** A concrete class that has never been passed to `alias()`, `share()`, `delegate()`, or `define()` returns `false` from `has()`, even though `make()` will succeed for it via auto-wiring. Do not use `has()` as a general "can I make this?" probe.

- **One prepare per name.** Calling `prepare(Foo::class, $callable)` a second time silently replaces the first callable. There is no stacking of multiple prepares for the same class or interface name.

- **Closures are not cached by the reflector.** `CachedReflection::getParameterTypeHint` explicitly skips caching when the function name contains `{closure}`. Closure parameter type-hints are always looked up fresh via `StandardReflection`.
