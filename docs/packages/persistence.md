# Persistence

> A thin Repository + UnitOfWork contract layered over **Cycle ORM v2**, wired through the framework's container, with a CLI for migrations and a scaffold extension for entity/repository/migration emission.

**Composer:** `univeros/persistence`
**Namespace:** `Altair\Persistence`

## Introduction

The framework deliberately does not ship its own ORM. Building one is a multi-year project, and the PHP ecosystem already has two good options. We pick **Cycle ORM v2** as the default because it is a true DataMapper (entities are plain PHP objects, no `ActiveRecord` parent class, no extends), is light on bytecode (no proxy compilation step like Doctrine), and its schema/migration model lines up well with code generation.

This package wraps Cycle. It does *not* replace it. The wrap exists for three reasons:

1. **A vendor-neutral contract.** `RepositoryInterface`, `UnitOfWorkInterface`, and `EntityManagerInterface` are framework-owned. Application code typehints these. If you ever need to swap Cycle out (Doctrine, an HTTP-backed store, a fake for tests), you replace the implementation under the contract — no change to call sites.
2. **A consistent DI story.** `CycleOrmConfiguration` reads `DB_*` env variables and binds the entire stack — `DatabaseManager`, `ORMInterface`, `UnitOfWorkInterface`, `EntityManagerInterface`, plus any domain-specific repositories — into `Altair\Container` in one call.
3. **First-class scaffolding.** The `univeros/scaffold` sub-package already turns a YAML endpoint spec into Action / Input / Responder / test / OpenAPI fragment / route entry. With a `persistence:` block on the same spec, you also get a Cycle-annotated entity, a typed repository, and a migration — emitted in lockstep, so the wire format and the storage shape stay in sync without manual coordination.

What this package deliberately does *not* do: it does not ship a query builder of its own (use Cycle's `Select` directly inside repositories when you need one), it does not embed a connection pool (rely on the underlying PDO driver), and it does not invent a new schema DSL (Cycle's annotations are the source of truth).

## Installation

Standalone:

```bash
composer require univeros/persistence
```

This pulls in `cycle/orm`, `cycle/database`, `cycle/migrations`, `cycle/schema-builder`, `cycle/annotated`, and `spiral/tokenizer` — Cycle's full standard stack. No PHP extensions beyond PDO are required for SQLite/MySQL/Postgres/SQL Server.

If you are installing the full framework, `composer require univeros/framework` already includes this package.

## Quick start

The smallest useful flow: declare DB env vars, supply a schema, ask for a repository.

```env
DB_CONNECTION=postgres
DB_HOST=localhost
DB_PORT=5432
DB_DATABASE=app
DB_USER=app
DB_PASSWORD=secret
```

```php
use Altair\Container\Container;
use Altair\Persistence\Configuration\CycleOrmConfiguration;
use Altair\Persistence\Contracts\EntityManagerInterface;
use Altair\Persistence\Schema\ArraySchemaProvider;
use Altair\Persistence\Schema\SchemaProviderInterface;

$container = new Container();
(new CycleOrmConfiguration())->apply($container);

// Application supplies the compiled schema. For real apps this comes from
// a build-time cache file; here we use an inline array.
$container->share(SchemaProviderInterface::class);
$container->delegate(
    SchemaProviderInterface::class,
    static fn(): SchemaProviderInterface => new ArraySchemaProvider(require __DIR__ . '/cache/cycle-schema.php'),
);

$em = $container->make(EntityManagerInterface::class);
$users = $em->repository(App\User\User::class);

$user = $users->find('uuid-here');
```

That is the full surface area for read-heavy code. For writes, use the unit of work or the repository's `save()` helper.

## Concepts

The package has five moving parts that map cleanly onto Cycle's runtime:

- **`RepositoryInterface<TEntity>`** — the contract every repository implements. Generic typing lives in PHPDoc so PHPStan narrows return types in subclasses; PHP itself does not need to know.
- **`UnitOfWorkInterface`** — wraps Cycle's transactional `EntityManager`. Schedule entities for insert/update/delete with `persist()` / `remove()`; commit with `flush()`. The unit of work resets itself after every flush so the same instance can be reused across requests.
- **`EntityManagerInterface<TEntity>`** — the top-level façade. Resolves a repository for any entity class — either a registered domain-specific repository (e.g. `UserRepository extends CycleRepository<User>`) or a generic adapter when no binding exists.
- **`SchemaProviderInterface`** — supplies the compiled Cycle schema. Two implementations ship: `ArraySchemaProvider` for build-time pre-compiled schemas (the production path), and `AttributeSchemaProvider` for tokenizer-driven discovery (good for dev and tests).
- **`DatabaseSettings`** — a `final readonly` value object built from env vars by `DatabaseSettings::fromEnv()`. `DatabaseConnectionFactory` turns it into a Cycle `DatabaseManager` configured for whichever driver you picked.

The lifecycle goes:

```
DB_* env vars  →  DatabaseSettings  →  DatabaseManager  →  ORMInterface  →  EntityManager  →  RepositoryInterface
                                                ↑
                                       SchemaProviderInterface
```

`CycleOrmConfiguration::apply()` wires every arrow except `SchemaProviderInterface` — the host application is responsible for binding that, because the schema source (cached PHP file vs. live attribute scan) is a per-app decision.

## Usage

### Defining an entity

Cycle uses PHP attributes. There is no base class.

```php
namespace App\User;

use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use DateTimeImmutable;

#[Entity(table: 'users')]
final class User
{
    #[Column(type: 'string(36)', primary: true)]
    public string $id;

    #[Column(type: 'string', unique: true)]
    public string $email;

    #[Column(type: 'string')]
    public string $passwordHash;

    #[Column(type: 'datetime', default: 'CURRENT_TIMESTAMP')]
    public DateTimeImmutable $createdAt;

    public function __construct(string $email, string $passwordHash)
    {
        $this->email = $email;
        $this->passwordHash = $passwordHash;
    }
}
```

You can write entities by hand, but you usually shouldn't — write a spec and let the scaffolder emit one (see [scaffold.md](./scaffold.md)).

### Domain-specific repositories

For anything beyond `find($id)`, subclass `CycleRepository<TEntity>` and add the typed query methods on the subclass:

```php
namespace App\User;

use Altair\Persistence\Contracts\UnitOfWorkInterface;
use Altair\Persistence\Cycle\CycleRepository;
use Cycle\ORM\ORMInterface;
use Cycle\ORM\Select\Repository as CycleSelectRepository;

/**
 * @extends CycleRepository<User>
 */
final class UserRepository extends CycleRepository
{
    public function __construct(ORMInterface $orm, UnitOfWorkInterface $unitOfWork)
    {
        parent::__construct(User::class, $orm, $unitOfWork);
    }

    public function findByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => $email]);
    }
}
```

Register the binding so `EntityManagerInterface::repository(User::class)` returns your subclass:

```php
(new CycleOrmConfiguration(repositoryBindings: [
    User::class => UserRepository::class,
]))->apply($container);
```

### Writes via the unit of work

For a single entity, `$repository->save($entity)` is fine — it persists and flushes in one call. For multi-entity transactions, drive the unit of work yourself:

```php
$uow = $em->unitOfWork();

$uow->persist($user);
$uow->persist($auditEntry);
$uow->remove($staleToken);
$uow->flush();   // single transaction
```

Cycle handles change tracking via its identity map. Once `flush()` returns, the unit of work is reset and ready to batch the next round.

### Schema providers

In production you want a pre-compiled schema on disk; live attribute scanning costs hundreds of milliseconds.

```php
// build step
$compiled = (new AttributeSchemaProvider($databases, [__DIR__ . '/src']))->schema();
file_put_contents(__DIR__ . '/cache/cycle-schema.php', '<?php return ' . var_export($compiled, true) . ';');

// runtime
$container->delegate(
    SchemaProviderInterface::class,
    static fn(): SchemaProviderInterface => new ArraySchemaProvider(require __DIR__ . '/cache/cycle-schema.php'),
);
```

In tests and during local development, bind `AttributeSchemaProvider` directly and let it recompile on each construction — convenient, slow, but the slowness rarely matters under `phpunit`.

### Read models

Cycle entities are mutable, managed objects. When a caller only needs to *read* — a view, an API response — hand it an immutable `Altair\Data\DataObjectInterface` instead. The entity manager is the entry point for the read side just as it is for writes:

```php
// $em is the EntityManagerInterface.
$users = $em->readModel(User::class, UserProfileDto::class);

$one  = $users->find(42);                       // ?UserProfileDto
$some = $users->findBy(['role' => 'admin']);    // list<UserProfileDto>
$all  = $users->findAll();                      // list<UserProfileDto>
```

`readModel()` returns a `ReadModelRepositoryInterface` — `find` / `findOneBy` / `findBy` / `findAll`, no writes. Writes stay on the entity `RepositoryInterface` and the unit of work; reads come back as Data objects. The Cycle implementation selects **raw rows** (`Select::fetchData()`) rather than managed entities, so reads skip the identity map — the right trade for the read side.

#### Coercion (the hydrator underneath)

Each row is projected through a `HydratorInterface` (`DataObjectHydrator` by default, bound in `CycleOrmConfiguration` and swappable). This is the bridge the Data package deliberately does not provide: `Data` assigns values as-is (its typed-property writes reject a mismatched type), so **type coercion lives here, in the persistence layer**, never in `Data`. The dependency arrow is one-way — `univeros/persistence` depends on `univeros/data`, never the reverse.

You can also use the hydrator directly on any array — a custom query result, a cache payload:

```php
use Altair\Persistence\Dto\DataObjectHydrator;

$hydrator = new DataObjectHydrator();

// A storage row — everything stringy, as a driver returns it.
$profile = $hydrator->hydrate(ProfileDto::class, [
    'id' => '42', 'name' => 'Vega', 'active' => '1', 'created_at' => '2026-01-15 09:00:00',
]);
// $profile->id === 42 (int), $profile->active === true (bool),
// $profile->created_at instanceof DateTimeImmutable

$many = $hydrator->hydrateMany(ProfileDto::class, $rows); // list<ProfileDto>
```

Coercion rules: numeric strings to `int`/`float`; `0`/`1`/`"true"`/`"false"` to `bool` (via `FILTER_VALIDATE_BOOLEAN`); scalars and `Stringable` to `string`; date strings/timestamps to `DateTimeImmutable`/`DateTime`. A property typed as another `DataObjectInterface` is hydrated recursively from a nested array — this is how composed read-models (the read side of a relation) are expressed:

```php
$row = ['id' => 1, 'address' => ['city' => 'New York', 'zip' => '10001']];
$profile = $hydrator->hydrate(ProfileDto::class, $row); // $profile->address instanceof AddressDto
```

`null` passes through untouched, keys with no matching property are dropped, and a value that cannot satisfy its declared type throws `HydrationException` (a `PersistenceExceptionInterface`) naming the offending field — rather than letting a raw `TypeError` escape from the constructor.

## CLI

The package ships four `bin/altair db:*` commands. They auto-load when the framework's CLI binary picks up `src/Altair/Persistence/Cli` (already wired in `bin/altair`).

```bash
bin/altair db:migrate                # apply every pending migration
bin/altair db:migrate --dry-run      # list pending migrations without applying them
bin/altair db:migrate --dir=path     # override migrations directory
bin/altair db:migrate:rollback       # roll back the last migration
bin/altair db:migrate:rollback --steps=3
bin/altair db:migrate:status         # list applied / pending; exit 1 if any pending
bin/altair db:schema-sync --entities=/abs/path/to/src/User,/abs/path/to/src/Order
bin/altair db:schema-sync --entities=...,... --dry-run
```

`db:schema-sync` diffs your entity attributes against the live database and applies changes directly, with no migration files involved. It is meant for development cycles where the entity shape is still in flux — **never** run it against production. Production migration changes go through `db:migrate` so the schema history is auditable and reversible.

## Scaffolding entities, repositories, and migrations

When a spec carries a `persistence:` block, the scaffolder emits three extra files alongside the action triple:

```yaml
# api/users/create.yaml
endpoint:
  method: POST
  path: /users
  ...

input:
  email:    { type: string, rules: [email, required] }
  password: { type: string, rules: [min:8, required], sensitive: true }

domain:
  class: App\User\CreateUser

persistence:
  entity:
    class: App\User\User
    table: users
    fields:
      id:            { type: uuid,     primary: true }
      email:         { type: string,   unique: true }
      password_hash: { type: string }
      created_at:    { type: datetime, default: now }
  repository: App\User\UserRepository
```

Run `bin/altair spec:scaffold api/users/create.yaml` and you get:

```
app/Http/Actions/CreateUserAction.php       # existing scaffold output
app/Http/Inputs/CreateUserInput.php
app/Http/Responders/CreateUserResponder.php
app/User/CreateUser.php
tests/Http/Actions/CreateUserActionTest.php
docs/openapi/create-user.yaml
config/routes.php                           # appended

app/User/User.php                           # NEW — Cycle-annotated entity
app/User/UserRepository.php                 # NEW — extends CycleRepository<User>
database/migrations/20260428_120000_create_users.php   # NEW — Cycle migration
```

The migration is **generated, not auto-applied**. Run `bin/altair db:migrate` explicitly. Hand-editing any of the three persistence files is fine, but follow it with `bin/altair spec:lint` so drift surfaces in CI (see [scaffold.md](./scaffold.md)).

## Testing

Unit tests for repositories work best against an **in-memory SQLite** database — fast, deterministic, no Docker dependency. The package's own test suite uses this pattern:

```php
use Altair\Persistence\Configuration\DatabaseConnectionFactory;
use Altair\Persistence\Configuration\DatabaseSettings;
use Altair\Persistence\Cycle\CycleRepository;
use Altair\Persistence\Cycle\CycleUnitOfWork;
use Cycle\ORM\Factory;
use Cycle\ORM\ORM;
use Cycle\ORM\Schema;
use Cycle\ORM\SchemaInterface;

$databases = (new DatabaseConnectionFactory())->create(new DatabaseSettings(
    driver: DatabaseSettings::DRIVER_SQLITE,
    database: ':memory:',
));

$databases->database('default')->execute(
    'CREATE TABLE widgets (id INTEGER PRIMARY KEY, name TEXT NOT NULL)'
);

$orm = new ORM(new Factory($databases), new Schema([/* …minimal schema… */]));
$uow = new CycleUnitOfWork($orm);
$repository = new CycleRepository(Widget::class, $orm, $uow);
```

See `tests/Persistence/Cycle/CycleRepositoryTest.php` for the full pattern.

For integration tests against real Postgres or MySQL, run them in CI with service containers. (The CI matrix wiring is deferred to a follow-up.)

## Extending

There are three natural extension points:

- **Custom repositories.** Subclass `CycleRepository<TEntity>` and add typed query methods. The base class exposes the entity class, the ORM, and the unit of work — that is everything you need to construct a `Cycle\ORM\Select` query.
- **Custom schema providers.** Implement `SchemaProviderInterface`. Useful when the schema lives somewhere unusual: a database-side configuration table, a remote service, a multi-tenant cache.
- **Custom connection factories.** `DatabaseConnectionFactory` knows the four common drivers (Postgres / MySQL / SQLite / SQL Server). For exotic drivers — Snowflake, ClickHouse, a sharded setup — build the `DatabaseManager` yourself and bind it to the container before calling `CycleOrmConfiguration::apply()`. The configuration will leave your binding intact.

What you should **not** extend: `CycleEntityManager` is `final`. Replace it via interface binding if you need a different EntityManager strategy. `DatabaseSettings` is `final readonly` by design — wrap, do not subclass.

## Related packages

- [scaffold.md](./scaffold.md) — the spec-driven code generator. With a `persistence:` block it also emits entities, repositories, and migrations.
- [container.md](./container.md) — the DI container `CycleOrmConfiguration` writes into.
- [configuration.md](./configuration.md) — the `ConfigurationInterface` contract and the `Env` value object the configuration reads from.
- [cli.md](./cli.md) — the attribute-driven CLI runtime that hosts `db:migrate` and friends.

## Limitations

- **Read replicas / shard routing.** Cycle's `DatabaseManager` can hold multiple connections, but the framework's `DatabaseSettings`/`DatabaseConnectionFactory` only models a single `default` connection. Multi-connection setups require constructing `DatabaseManager` yourself.
- **Soft deletes / audit trails.** Cycle has packages for these (`cycle/entity-behavior`, `cycle/entity-behavior-uuid`); they are not enabled by default. Add them to your composer and configure on the entity attributes directly.
- **Doctrine bridge.** Not in this package. A separate `univeros/doctrine` could implement the same contracts; the wrap is already shaped for it.
- **`--dry-run` SQL preview.** `db:migrate --dry-run` currently lists pending migration names. A per-migration SQL dump is a follow-up — Cycle's `Migrator` does not expose a non-destructive SQL preview out of the box.
- **Postgres / MySQL CI matrix.** Tests today exercise in-memory SQLite. Real-driver integration tests are tracked as a follow-up on the original issue.
- **Read-model scope.** `readModel()` projects a single entity role's rows; joined/eager-loaded relations are not auto-mapped into nested Data objects yet (the hydrator *will* compose a nested `DataObjectInterface` when a row already carries the relation as an array — e.g. from a custom `Select` with `->load()`). The hydrator coerces against a single declared property type; union and intersection types are passed through for PHP to enforce.
