# Configuration

A composable, container-aware configuration system that loads `.env` files via vlucas/phpdotenv 5 and wires dependency bindings through a unified interface.

---

## Composer and namespace

```
Package:    univeros/configuration
Namespace:  Altair\Configuration
Requires:   php >=8.3, vlucas/phpdotenv ^5.6,
            univeros/container ^2.0, univeros/structure ^2.0
```

---

## Introduction

Most PHP applications reach for a plain associative array when they need configuration. That approach works for small projects, but it leaves you writing the same bootstrapping glue in every entry point and scattering `$_ENV` calls throughout the codebase. This package takes a different position: configuration is an operation applied to a dependency-injection container, not a data structure you pass around.

Every configuration class in this package implements `ConfigurationInterface`, which exposes a single method: `apply(Container $container): void`. When you call `apply`, the configuration registers whatever it manages — environment variables, service bindings, shared instances — into the container. Consumers then ask the container for what they need, and the container delivers it fully initialised.

`EnvironmentConfiguration` is the entry point for `.env` support. It wraps vlucas/phpdotenv 5 and, when applied, registers a shared `Env` service in the container. That service knows how to read variables from `$_ENV`, `$_SERVER`, and `getenv()` in that priority order. Dotenv 5 loads values lazily: the `.env` file is parsed only when the `Env` service is first resolved from the container.

`ConfigurationCollection` is the composition primitive. It holds an ordered set of configuration objects or class-name strings. When you call `apply` on the collection, it iterates the set and delegates to each member in turn. Class-name strings are resolved through the container itself, which means you can pre-define constructor arguments using `Container::define` before the collection runs. This makes it easy to assemble a full application bootstrap from modular pieces without coupling those pieces to each other.

The separation between "loading env vars" and "registering bindings" is intentional. You apply `EnvironmentConfiguration` first so that env values are present by the time subsequent configurations read them. The `EnvAwareTrait` provides a standard constructor signature for any configuration class that needs access to `Env`.

---

## Installation

```bash
composer require univeros/configuration
```

phpdotenv 5 is a direct dependency and is installed automatically. You do not need to require it separately.

---

## Quick start

This example shows why you load `EnvironmentConfiguration` before any other configuration: subsequent configurations can then read from the `Env` service that it registers.

```php
<?php

declare(strict_types=1);

use Altair\Configuration\Collection\ConfigurationCollection;
use Altair\Configuration\EnvironmentConfiguration;
use Altair\Configuration\Support\Env;
use Altair\Container\Container;
use Altair\Container\Definition;

$container = new Container();

// Tell the container how to instantiate EnvironmentConfiguration.
$container->define(
    EnvironmentConfiguration::class,
    new Definition([':filePath' => __DIR__ . '/.env']),
);

// Compose and apply.
$configs = new ConfigurationCollection([
    EnvironmentConfiguration::class,
    YourDatabaseConfiguration::class, // reads DB_HOST etc. via Env
]);
$configs->apply($container);

// Env is now a shared service.
$env = $container->make(Env::class);
echo $env->get('APP_NAME');                     // 'MyApp'
echo $env->get('MISSING_KEY', 'fallback');      // 'fallback'
```

---

## Concepts

### ConfigurationInterface

Every configuration class implements `Altair\Configuration\Contracts\ConfigurationInterface`:

```php
interface ConfigurationInterface
{
    public function apply(Container $container): void;
}
```

`apply` receives the application container and uses it to register shared instances, delegate factories, or preparation callbacks. Nothing is returned — the container is mutated in place. This is the single extension point the package defines.

### ConfigurationCollection — composing configurations

`ConfigurationCollection` extends `Altair\Structure\Set` and itself implements `ConfigurationInterface`. Because it implements the same interface as its members, collections can nest: a collection of collections is valid.

Each member can be either a class-name string or an already-constructed object. When the collection encounters a string, it calls `$container->make($classname)` to instantiate it. Pre-define constructor arguments with `Container::define` before calling `apply`; the collection will honour those definitions.

If a member (whether passed as a string or an object) does not implement `ConfigurationInterface`, the collection throws `InvalidConfigurationException` immediately, stopping the application before it reaches an inconsistent state.

### EnvironmentConfiguration — env loading

`EnvironmentConfiguration` wraps phpdotenv 5. Its constructor accepts a full file path and an `$immutable` flag (default `true`). With `$immutable = true` it calls `Dotenv::createImmutable`, which respects variables that are already set in the environment — a shell-level export or a testing fixture will not be overwritten. With `$immutable = false` it calls `Dotenv::createMutable`, which always overwrites.

The constructor validates the file path immediately and throws `InvalidArgumentException` if the file does not exist or is not readable. This check happens at construction time, not at `apply` time, so misconfigured paths fail early — before any container work begins.

When `apply` is called:

1. `Env::class` is registered as a shared service.
2. `Dotenv::class` is registered with a delegate factory that creates the correct immutable or mutable instance.
3. A preparation callback on `Env::class` calls `$container->make(Dotenv::class)->load()`. The `.env` file is parsed at this point.

### Env — the value accessor

`Altair\Configuration\Support\Env` is the runtime accessor for environment variables. It checks `$_ENV`, then `$_SERVER`, then `getenv()`, and returns the first match. It never throws on a missing key; pass a second argument as the default value.

`Env` does not perform type coercion. All values it returns are strings (or your default). Convert values to `int`, `bool`, etc. in the configuration class that consumes them.

### EnvAwareTrait — injection helper

`Altair\Configuration\Traits\EnvAwareTrait` provides a standard constructor that accepts `Env` and assigns it to `$this->env`. Use it in any configuration class that reads environment variables directly, so the container can inject `Env` automatically.

---

## Usage

### Reading environment values

After `EnvironmentConfiguration` has been applied and the container has resolved `Env`, read values through the service:

```php
// Resolve Env from the container whenever you need it,
// or inject it via the container into your configuration classes.
$env = $container->make(Env::class);

$dsn      = $env->get('DATABASE_URL');
$debug    = $env->get('APP_DEBUG', 'false');
$timeout  = (int) $env->get('CACHE_TTL', '60');
```

Variable interpolation in the `.env` file works as phpdotenv 5 defines it. Quoted strings with `${VAR}` references are expanded at load time:

```ini
NVAR1="Hello"
NVAR2="World!"
NVAR3="${NVAR1} ${NVAR2}"   # resolves to "Hello World!"
```

### Chained configurations and fallback order

Build a `ConfigurationCollection` with members in the order you want them applied. Earlier members run first, so an `EnvironmentConfiguration` must precede any configuration that reads from `Env`.

```php
// DatabaseConfiguration and CacheConfiguration both use EnvAwareTrait,
// so the container injects Env into them automatically after
// EnvironmentConfiguration has registered it as a shared service.
$configs = new ConfigurationCollection([
    EnvironmentConfiguration::class,
    DatabaseConfiguration::class,
    CacheConfiguration::class,
]);
$configs->apply($container);
```

Because `ConfigurationCollection` extends `Set` (which stores unique values), adding the same class name twice has no effect — the second occurrence is silently discarded.

### Loading environment variables

Pass the full path to the `.env` file via `Container::define` before applying the collection:

```php
$container->define(
    EnvironmentConfiguration::class,
    new Definition([':filePath' => dirname(__DIR__) . '/.env']),
);
```

For a mutable load (environment variables are overwritten rather than protected):

```php
$container->define(
    EnvironmentConfiguration::class,
    new Definition([
        ':filePath'  => dirname(__DIR__) . '/.env',
        ':immutable' => false,
    ]),
);
```

### Writing a container configuration class

A container configuration class registers services and bindings. It has nothing to do with `.env` files; it uses `ConfigurationInterface` purely for the composable apply-to-container pattern.

```php
<?php

declare(strict_types=1);

namespace App\Configuration;

use Altair\Configuration\Contracts\ConfigurationInterface;
use Altair\Configuration\Support\Env;
use Altair\Configuration\Traits\EnvAwareTrait;
use Altair\Container\Container;
use App\Database\Connection;
use App\Database\ConnectionInterface;

class DatabaseConfiguration implements ConfigurationInterface
{
    use EnvAwareTrait; // injects Env via constructor

    #[\Override]
    public function apply(Container $container): void
    {
        $dsn  = $this->env->get('DATABASE_URL', 'sqlite::memory:');
        $user = $this->env->get('DATABASE_USER', '');
        $pass = $this->env->get('DATABASE_PASS', '');

        $container
            ->alias(ConnectionInterface::class, Connection::class)
            ->delegate(
                Connection::class,
                fn (): Connection => new Connection($dsn, $user, $pass),
            );
    }
}
```

The container resolves `EnvAwareTrait`'s constructor argument (`Env`) automatically because `EnvironmentConfiguration` registered it as a shared instance first.

---

## Configuration classes in this package

This package ships one built-in configuration class:

| Class | What it does | Key constructor args |
|---|---|---|
| `Altair\Configuration\EnvironmentConfiguration` | Loads a `.env` file, registers `Env` as a shared service | `$filePath` (required), `$immutable` (default: `true`) |

There are no pre-built configuration classes for database, cache, or other infrastructure. Those belong in your application or in the sub-packages that own those services (see [container.md](./container.md)).

---

## Testing

### Fixture `.env` files

Place fixture env files in `tests/YourPackage/fixtures/`. The tests for this package use `good.env` and `wrong.env` as examples:

```ini
# tests/Configuration/fixtures/good.env
FOO=bar
BAR=baz
NVAR1="Hello"
NVAR2="World!"
NVAR3="${NVAR1} ${NVAR2}"
```

A file with unquoted values that contain spaces (`SPACED=with spaces`) triggers phpdotenv 5's `InvalidFileException` when the file is loaded — phpdotenv 5 requires that values with spaces be wrapped in quotes.

### Seeding env vars in tests

The cleanest approach is to rely on a real fixture file and let `EnvironmentConfiguration` load it via the container. This exercises the full path from file to `Env::get`:

```php
private function prepareContainer(string $filePath): Container
{
    $container = new Container();
    $container->define(
        EnvironmentConfiguration::class,
        new Definition([':filePath' => $filePath]),
    );
    $configuration = new ConfigurationCollection([EnvironmentConfiguration::class]);
    $configuration->apply($container);

    return $container;
}

public function testReadsValues(): void
{
    $env = $this->prepareContainer(__DIR__ . '/fixtures/good.env')
                ->make(Env::class);

    $this->assertSame('bar', $env->get('FOO'));
    $this->assertSame('This is default', $env->get('MISSING', 'This is default'));
}
```

When you need to inject env vars without a file — for example in a unit test for a configuration class that uses `EnvAwareTrait` — use `putenv()` in a `setUp`/`tearDown` pair and construct `Env` directly:

```php
protected function setUp(): void
{
    putenv('DB_HOST=localhost');
    putenv('DB_PORT=5432');
}

protected function tearDown(): void
{
    putenv('DB_HOST');
    putenv('DB_PORT');
}

public function testDatabaseConfigurationBindsConnection(): void
{
    $env       = new Env();
    $config    = new DatabaseConfiguration($env);
    $container = new Container();

    $config->apply($container);

    $this->assertInstanceOf(
        ConnectionInterface::class,
        $container->make(ConnectionInterface::class),
    );
}
```

Always clean up `putenv` calls in `tearDown`. Global env state leaks across tests when it is not restored.

---

## Extending

### Custom configuration sources

Implement `ConfigurationInterface` to add any configuration source — YAML files, a remote secrets manager, a database table. The only requirement is that your class can express its work as a set of container operations.

Below is a minimal YAML-backed configuration source. It reads a YAML file, converts the values to env-style strings, and registers the parsed data as a keyed parameter in the container.

```php
<?php

declare(strict_types=1);

namespace App\Configuration;

use Altair\Configuration\Contracts\ConfigurationInterface;
use Altair\Container\Container;

class YamlConfiguration implements ConfigurationInterface
{
    public function __construct(private readonly string $filePath) {}

    #[\Override]
    public function apply(Container $container): void
    {
        if (!is_readable($this->filePath)) {
            throw new \RuntimeException("Cannot read YAML file: {$this->filePath}");
        }

        $data = \Symfony\Component\Yaml\Yaml::parseFile($this->filePath);

        // Register each top-level key as a shared scalar in the container.
        foreach ($data as $key => $value) {
            $container->share($key, $value);
        }
    }
}
```

Add it to a `ConfigurationCollection` just like any other configuration:

```php
$configs = new ConfigurationCollection([
    EnvironmentConfiguration::class,
    YamlConfiguration::class,
]);
```

---

## Recipes

### Layered prod-vs-local configuration

Keep a committed `.env.example` that declares all required variables with empty or safe defaults. Each environment has its own real `.env` that is gitignored. At bootstrap, choose the file based on the `APP_ENV` variable already present in the shell:

```php
$envFile = match (getenv('APP_ENV') ?: 'production') {
    'local', 'development' => __DIR__ . '/.env.local',
    'testing'              => __DIR__ . '/.env.testing',
    default                => __DIR__ . '/.env',
};

$container->define(
    EnvironmentConfiguration::class,
    new Definition([':filePath' => $envFile]),
);
```

This pattern works because `EnvironmentConfiguration` validates the path at construction time. A missing file raises `InvalidArgumentException` before the container is used.

### Loading secrets without overwriting the shell environment

Pass `$immutable = true` (the default) when you deploy to a platform that injects secrets as real environment variables. Dotenv 5 in immutable mode skips any variable that is already set, so a pre-existing `DATABASE_URL` in the process environment takes precedence over the `.env` file value:

```php
// immutable = true is the default; shown here explicitly for clarity.
$container->define(
    EnvironmentConfiguration::class,
    new Definition([
        ':filePath'  => __DIR__ . '/.env',
        ':immutable' => true,
    ]),
);
```

Use `$immutable = false` only in local development where you want the `.env` file to be the authoritative source and you want to reset variables that a parent shell may have set.

### Wiring container bindings from env values

A configuration class that reads env vars and registers bindings brings together both sides of the package. The key is to apply `EnvironmentConfiguration` first so that `Env` is available for injection:

```php
$configs = new ConfigurationCollection([
    EnvironmentConfiguration::class,  // registers Env
    RedisConfiguration::class,        // reads REDIS_HOST via Env, registers Redis client
    SessionConfiguration::class,      // reads SESSION_DRIVER via Env, aliases handler
    AppConfiguration::class,          // reads APP_KEY etc. via Env, binds app services
]);
$configs->apply($container);
```

Each configuration class is instantiated by the container, which means the container injects `Env` automatically into any class that uses `EnvAwareTrait`.

---

## Related packages

- [container.md](./container.md) — `Altair\Container\Container` is the target of every `apply` call. Understanding `share`, `alias`, `delegate`, and `define` is essential for writing configuration classes.
- [common.md](./common.md) — `Altair\Common` and `Altair\Structure` provide the `Set` base class that `ConfigurationCollection` extends.

---

## Migration notes

### Dotenv v2 → v5 (completed in Phase 3b, 2026-05)

The original `EnvironmentConfiguration` used the `Dotenv\Loader` class directly, which was internal and removed in Dotenv 3. The current implementation uses only the stable public API:

- `Dotenv::createImmutable($dir, $file)` replaces the old `new Dotenv($dir, $file)` constructor.
- `Dotenv::createMutable($dir, $file)` is the explicit opt-in for overwriting existing env vars. Previously the overwrite behaviour was the default and could not be controlled.
- The immutable mode in Dotenv 5 means variables already set in the environment are not changed. Applications that relied on `.env` values overriding shell exports must now pass `$immutable = false`.
- Dotenv 5 throws `Dotenv\Exception\InvalidFileException` when a line cannot be parsed (for example, an unquoted value containing spaces). The old loader was more permissive. Review your `.env` files if you are migrating from an older codebase.
- There is no `Dotenv::required()->notEmpty()` call in the built-in `EnvironmentConfiguration`. If you need required-variable validation, add it inside your own configuration class's `apply` method using phpdotenv 5's `$dotenv->required([...])` fluent API after calling `load()`.

---

## Limitations

- **No schema validation.** The package does not define or enforce the shape of your configuration. Type casting, range checking, and required-key validation are the responsibility of individual configuration classes.
- **No encrypted secrets.** Values are read from `.env` files and standard environment variables as plain strings. Integrate a secrets manager (HashiCorp Vault, AWS Secrets Manager, etc.) by writing a custom `ConfigurationInterface` implementation.
- **No caching.** The `.env` file is parsed on every cold start. For performance-sensitive environments, pre-bake configuration values into real environment variables at deploy time (the immutable mode will then skip the file for those keys).
- **No typed getters on Env.** `Env::get` always returns a string or your default. Casting to `int`, `bool`, or `float` is the caller's responsibility.
- **Set deduplication.** `ConfigurationCollection` extends `Altair\Structure\Set`, which stores unique values. Duplicate class-name strings are silently dropped. If you need the same configuration applied twice with different arguments, instantiate two separate objects rather than registering the class name twice.
