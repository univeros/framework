# Module

> Pluggable extension modules for Univeros. A module is a single class a host app registers in `config/modules.php`; from that one line it self-registers its HTTP routes, PSR-15 middleware, Cycle entities, and migrations — no other host file changes.

**Composer:** `univeros/module`
**Namespace:** `Altair\Module`

## Introduction

A host Altair app can already `composer require` a package and pull its services into the container through a [Configuration](./configuration.md). What it could *not* do — until this package — is discover a third-party package's **routes**, **middleware**, **entities**, and **migrations**, which live in fixed host locations (`config/routes.php`, the hand-assembled Relay pipeline in `public/index.php`, the host's entity directories, the single `database/migrations/`). That gap made a "voilà, the whole feature is wired" extension impossible: the host always had to hand-edit several files.

The Module package closes that gap with a thin, opt-in convention built entirely on parts the framework already has — `ConfigurationInterface`, the container's `tag()`/`tagged()`, the [Http](./http.md) route list, the multi-directory `AttributeSchemaProvider`, and Cycle's shared `cycle_migrations` tracking table. There is no new runtime engine and no auto-magic file scanning: a host registers module *instances* explicitly in `config/modules.php`, and the framework's existing consumers (the front controller, the schema provider, the `db:migrate` commands) pick the contributions up.

A module is, first, a `ModuleInterface` — which is just a `ConfigurationInterface` with a `name()`. It becomes more by *also* implementing one or more narrow capability contracts. Implement only what you ship; a service-only module need not depend on `univeros/http` or `univeros/persistence` at all.

## Installation

```bash
composer require univeros/module
```

It depends only on [Container](./container.md) and [Configuration](./configuration.md). The capability contracts a module implements pull in their own packages (`univeros/http` for routes, `univeros/persistence` for entities/migrations) only when you use them.

## Quick start

Scaffold a module package and register it — that is the whole loop:

```bash
bin/altair module:new --dir=user-management --name=acme/user-management
```

```php
// config/modules.php in the host app
return [
    new Acme\UserManagement\Module(),
];
```

That single registration wires the module's `GET /sample` route, its `SampleEntity` into the ORM schema, and its migration into `bin/altair db:migrate`.

## Concepts

### `ModuleInterface`

```php
namespace Altair\Module\Contracts;

interface ModuleInterface extends ConfigurationInterface
{
    public function name(): string;          // e.g. "acme/user-management"
    // public function apply(Container $container): void;  // inherited — bind services
}
```

`ModuleConfiguration` registers a list of modules. For each one it binds the instance and tags it `altair.module`, then calls the module's `apply()`:

```php
// config/container.php (generated apps do this for you)
$modules = require __DIR__ . '/modules.php';
(new ModuleConfiguration($modules))->apply($container);
```

### Capability contracts (opt-in)

A module advertises what it contributes by implementing extra interfaces. Consumers collect tagged modules and branch on `instanceof`, so unimplemented capabilities cost nothing.

| Contract | Method | Consumed by |
|---|---|---|
| `RoutesProviderInterface` | `routes(): list<array{0,1,2}>` | the front controller, via `Altair\Http\Support\ModuleRoutes::collect()` |
| `MiddlewareProviderInterface` | `middleware(): list<array{middleware, priority}>` | the front controller, via `Altair\Http\Support\ModuleMiddleware::collect()` |
| `EntityDirectoriesProviderInterface` | `entityDirectories(): list<string>` | `Altair\Persistence\Schema\ModuleAwareSchemaProvider` |
| `MigrationDirectoriesProviderInterface` | `migrationDirectories(): list<MigrationSource>` | `bin/altair db:migrate` / `:status` / `:rollback` |

Routes use the same `[METHOD, PATH, Action::class]` shape as `config/routes.php`. Each `MigrationSource` pairs a directory with the migration namespace your classes are declared in (e.g. `Acme\UserManagement\Database\Migrations`) — Cycle reads each migration's FQCN from the file, so per-module namespaces never collide. Middleware entries each pair a PSR-15 middleware (a `class-string` resolved through the container, or an instance) with an integer `priority`, ordered against the documented anchors in `Altair\Http\Support\MiddlewarePriority` (`EXCEPTION_HANDLER` = 0, `DISPATCHER` = 500, `ACTION` = 1000).

### How the contributions are picked up

- **Routes** — the generated `public/index.php` calls `ModuleRoutes::collect($container, $hostRoutes)`, which appends every tagged module's `routes()` to the host's. Host routes come first, so a host can always override.
- **Middleware** — the generated `public/index.php` calls `ModuleMiddleware::collect($container, $baseStages)`, which appends every tagged module's `middleware()` to the framework's base pipeline and stable-sorts the result by priority (equal priorities keep input order, so the merge is deterministic). The resulting queue is handed to Relay with a `ContainerResolver`, so class-string middleware are instantiated — and their dependencies autowired — at dispatch time. A module can thus register an auth / rate-limit / idempotency guard without the host editing `public/index.php`.
- **Entities** — bind `SchemaProviderInterface` to `ModuleAwareSchemaProvider`; it compiles the schema from the host's entity directories plus every module's `entityDirectories()`.
- **Migrations** — the migrate commands pass each module's directory to Cycle's `MigrationConfig` as a `vendorDirectories` entry, so one migrator runs the host's `database/migrations` and every module's directory against the shared tracking table, with correct ordering, status, and rollback.

## Authoring a module

`bin/altair module:new --name=acme/user-management` emits a complete, testable package:

```
src/Module.php                     implements ModuleInterface + the capability contracts
src/Domain/SampleService.php       business logic behind GET /sample
src/Http/Actions|Inputs|Responders sample Action → Domain → Responder
src/Entity/SampleEntity.php        a Cycle-annotated entity
database/migrations/               a sample migration
tests/ModuleTest.php               proves the module wires up
```

Drop a capability by removing its interface from the `implements` list in `src/Module.php`. Derive the namespace from the package name automatically (`acme/user-management` → `Acme\UserManagement`) or override with `--namespace`.

## Testing notes

The package's own tests construct a `Container`, apply a `ModuleConfiguration`, and assert that modules are tagged and that the collect helpers (`ModuleRoutes`, `ModuleEntityDirectories`, `ModuleMigrationDirectories`) merge contributions. The persistence integration tests run a generated module's migration against in-memory SQLite and compile a module's entity into a live schema — proving the "register one class" promise end to end.

## Related

- [Configuration](./configuration.md) — the `apply(Container)` contract a module extends.
- [Container](./container.md) — the `tag()`/`tagged()` mechanism modules are discovered through.
- [Http](./http.md) — the route list a module contributes to.
- [Persistence](./persistence.md) — the schema provider and `db:migrate` commands that pick up module entities and migrations.
- [Bootstrap](./bootstrap.md) — `bin/altair module:new`, which scaffolds a module package (and `bin/altair new`, which scaffolds the host).
- [Extending Altair](../guides/extending.md) — the end-to-end guide to building an extension.
