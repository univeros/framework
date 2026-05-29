# univeros/persistence  ·  Altair\Persistence

**Purpose:** Thin Repository + UnitOfWork contract over Cycle ORM v2, wired through Altair's Container.

## Public contracts

| Interface | Method | Returns | Notes |
|---|---|---|---|
| `EntityManagerInterface` | `readModel(string, string)` | `ReadModelRepositoryInterface` |  |
|  | `repository(string)` | `RepositoryInterface` |  |
|  | `unitOfWork()` | `UnitOfWorkInterface` |  |
| `HydratorInterface` | `hydrate(string, array)` | `DataObjectInterface` |  |
|  | `hydrateMany(string, iterable)` | `array` |  |
| `ReadModelRepositoryInterface` | `find(string\|int)` | `DataObjectInterface\|null` |  |
|  | `findAll()` | `array` |  |
|  | `findBy(array)` | `array` |  |
|  | `findOneBy(array)` | `DataObjectInterface\|null` |  |
| `RepositoryInterface` | `delete(object)` | `void` |  |
|  | `find(string\|int)` | `object\|null` |  |
|  | `findAll()` | `iterable` |  |
|  | `findBy(array)` | `iterable` |  |
|  | `findOneBy(array)` | `object\|null` |  |
|  | `save(object)` | `void` |  |
| `UnitOfWorkInterface` | `clear()` | `void` |  |
|  | `flush()` | `void` |  |
|  | `persist(object)` | `void` |  |
|  | `remove(object)` | `void` |  |

## Concrete classes

- `ArraySchemaProvider` _(final)_ — implements `SchemaProviderInterface`
- `AttributeSchemaProvider` _(final)_ — implements `SchemaProviderInterface`
- `CollectionOf` _(final)_
- `CycleEntityManager` _(final)_ — implements `EntityManagerInterface`
- `CycleOrmConfiguration` _(final)_ — implements `ConfigurationInterface`
- `CycleReadModelRepository` _(final)_ — implements `ReadModelRepositoryInterface`
- `CycleRepository` — implements `RepositoryInterface`
- `CycleUnitOfWork` _(final)_ — implements `UnitOfWorkInterface`
- `DataObjectHydrator` _(final)_ — implements `HydratorInterface`
- `DatabaseConnectionFactory` _(final)_
- `DatabaseSettings` _(final)_
- `MigrateCommand` _(final)_
- `MigrateRollbackCommand` _(final)_
- `MigrateStatusCommand` _(final)_
- `MigrationConfigFactory` _(final)_
- `MigrationPathResolver` _(final)_
- `MigratorFactory` _(final)_
- `SchemaSyncCommand` _(final)_

## Tests as documentation

- `tests/Persistence/Configuration/DatabaseConnectionFactoryTest.php`
- `tests/Persistence/Configuration/DatabaseSettingsTest.php`
- `tests/Persistence/Cycle/CycleEntityManagerTest.php`
- `tests/Persistence/Cycle/CycleReadModelRelationTest.php`
- `tests/Persistence/Cycle/CycleReadModelRepositoryTest.php`
- `tests/Persistence/Cycle/CycleRepositoryTest.php`
- `tests/Persistence/Dto/DataObjectHydratorTest.php`

## Related packages

- `cycle/annotated`
- `cycle/database`
- `cycle/migrations`
- `cycle/orm`
- `cycle/schema-builder`
- `spiral/tokenizer`
- `univeros/cli`
- `univeros/configuration`
- `univeros/container`
- `univeros/data`
