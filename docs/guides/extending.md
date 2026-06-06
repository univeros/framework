# Extending Altair: building a module

A **module** is a pluggable, installable feature for a Univeros app — analogous to a Laravel package with a service provider or a Symfony bundle. A host `composer require`s it, adds one line to `config/modules.php`, and the module's HTTP routes, Cycle entities, and migrations are wired in. Nothing else in the host changes.

This guide shows how to build one. The mechanism is intentionally small: it reuses [`ConfigurationInterface`](../packages/configuration.md), the container's [tagging](../packages/container.md), the [Http](../packages/http.md) route list, the multi-directory schema provider, and Cycle's shared migration table. See [the Module package reference](../packages/module.md) for the contract details.

## 1. Scaffold

```bash
bin/altair module:new --dir=user-management --name=acme/user-management
```

You get a complete, testable package (`acme/user-management`, namespace `Acme\UserManagement` derived from the name — override with `--namespace`):

```
src/Module.php                         the entry point a host registers
src/Domain/SampleService.php           business logic behind GET /sample
src/Http/Actions/SampleAction.php      Action → Domain → Responder wiring
src/Http/Inputs/SampleInput.php
src/Http/Responders/SampleResponder.php
src/Entity/SampleEntity.php            a Cycle-annotated entity
database/migrations/                   a sample migration
tests/ModuleTest.php                   proves the module wires up
composer.json  README.md  phpunit.xml.dist
```

```bash
cd user-management && composer install && vendor/bin/phpunit
```

## 2. The module class

`src/Module.php` is the entry point. It implements `ModuleInterface` (a `ConfigurationInterface` plus `name()`) and *opts into* capabilities by also implementing the provider contracts:

```php
final class Module implements
    ModuleInterface,                        // required
    RoutesProviderInterface,                // ships routes
    EntityDirectoriesProviderInterface,     // ships entities
    MigrationDirectoriesProviderInterface   // ships migrations
{
    public function name(): string { return 'acme/user-management'; }

    public function apply(Container $container): void
    {
        // Register the module's services. The container auto-wires concretes,
        // so you only bind interfaces, factories, and shared singletons.
        $container->singleton(UserService::class);
    }

    public function routes(): array
    {
        return [['POST', '/users', CreateUserAction::class]];
    }

    public function entityDirectories(): array
    {
        return [__DIR__ . '/Entity'];
    }

    public function migrationDirectories(): array
    {
        return [new MigrationSource(
            \dirname(__DIR__) . '/database/migrations',
            __NAMESPACE__ . '\\Database\\Migrations',
        )];
    }
}
```

**Implement only what you ship.** A module that contributes only services drops the three provider interfaces and need not depend on `univeros/http` or `univeros/persistence` at all.

## 3. Register it in a host

```bash
composer require acme/user-management
```

```php
// config/modules.php
return [
    new Acme\UserManagement\Module(),
];
```

That is the entire installation. Here is what each capability does and how it is picked up:

### Routes — automatic

The generated `public/index.php` already merges module routes:

```php
$routes = ModuleRoutes::collect($container, require '.../config/routes.php');
```

Host routes come first, so a host can override any route a module would add.

### Middleware — automatic

A module that needs a PSR-15 guard — authentication, rate-limiting, tenant resolution, an action-aware idempotency check — implements `MiddlewareProviderInterface`:

```php
use Altair\Http\Support\MiddlewarePriority;
use Altair\Module\Contracts\MiddlewareProviderInterface;

final class Module implements ModuleInterface, MiddlewareProviderInterface
{
    public function middleware(): array
    {
        return [
            // Runs after routing, before the action — so it can read the
            // matched action off the request (an action-aware guard).
            ['middleware' => AuthGuard::class, 'priority' => MiddlewarePriority::DISPATCHER + 10],
        ];
    }
}
```

The generated `public/index.php` already merges these:

```php
$pipeline = ModuleMiddleware::collect($container, [ /* base stages with priorities */ ]);
$relay = new Relay($pipeline, new ContainerResolver($container));
```

**Ordering is by integer `priority`** — lower runs earlier / more outer — against three documented anchors for the framework's own stages in `Altair\Http\Support\MiddlewarePriority`:

| Anchor | Value | Stage |
|---|---|---|
| `EXCEPTION_HANDLER` | `0` | outermost; turns any throwable into a response |
| `DISPATCHER` | `500` | matches the route, records the action on the request |
| `ACTION` | `1000` | innermost; resolves and runs the matched action |

Slot a pre-routing guard (CORS, rate-limit) below `DISPATCHER`; slot an action-aware guard (auth, idempotency) between `DISPATCHER` and `ACTION`. Keep ordinary guards strictly between the anchors — `ACTION` is the innermost stage and is *terminal* on a matched route, so a priority `>= ACTION` never runs once a route matches; a priority below `EXCEPTION_HANDLER` runs *outside* the exception handler (reserve it for a deliberate outermost wrapper). The merge is a **stable sort**: equal priorities keep input order — base stages first, then modules in registration order — so the assembled pipeline is fully deterministic. A class-string entry is resolved through the container at dispatch time, so the middleware's own dependencies are autowired; you may also pass a ready-made instance.

`bin/altair middleware:list` shows the merged pipeline (when the host binds it as the `MiddlewareCollection`), so module middleware appear at their resolved position.

### Migrations — automatic

`bin/altair db:migrate` (and `:status` / `:rollback`) collect every registered module's migration directory and pass them to Cycle as `vendorDirectories`. One migrator runs the host's `database/migrations` plus every module's directory against the shared `cycle_migrations` table — correct ordering, status, and rollback, applied-once semantics included.

> **Migration namespace convention.** Give each module its own migration namespace (e.g. `Acme\UserManagement\Database\Migrations`, as the scaffold does via `__NAMESPACE__`). Cycle reads each migration's fully-qualified class name from the file, so distinct per-module namespaces guarantee no class-name collisions across modules.

### Entities — one host binding

Cycle needs a `SchemaProviderInterface`. To include module entities, bind the module-aware provider (instead of a bare `AttributeSchemaProvider`) wherever your host wires persistence — typically in a host `ConfigurationInterface`:

```php
$container->factory(
    SchemaProviderInterface::class,
    static fn(DatabaseProviderInterface $db, Container $c): SchemaProviderInterface
        => new ModuleAwareSchemaProvider($db, $c, baseDirectories: [__DIR__ . '/../app/Entity']),
)->shared();
```

It compiles the schema from your `baseDirectories` plus every registered module's `entityDirectories()`. This is the one capability that is a deliberate host choice rather than fully automatic, because the host owns how its schema is built (attribute discovery vs. a pre-compiled schema).

## 4. Test

The scaffolded `tests/ModuleTest.php` constructs the module and asserts its routes, bindings, and directories. Grow it as you add behaviour — the host doesn't need to be involved to test a module in isolation.

## 5. Publish

A module is an ordinary Composer package. Pick your own vendor and namespace — **do not** use `Altair\` (the first-party namespace) or a `univeros/*` name (those are the framework's own read-only splits). Tag a release and submit the repo to Packagist; hosts then `composer require acme/user-management` and add the one line above.

## What this is not

Registration is explicit — there is no composer-`extra` auto-discovery scanning installed packages. That is deliberate: a host's `config/modules.php` is the single, greppable source of truth for what is installed, in what order. If you want a module enabled, you name it there.

## See also

- [Module package reference](../packages/module.md) — the contracts in detail.
- [Bootstrap](../packages/bootstrap.md) — `module:new` and `new` scaffolders.
- [Http](../packages/http.md) · [Persistence](../packages/persistence.md) · [Container](../packages/container.md) · [Configuration](../packages/configuration.md).
