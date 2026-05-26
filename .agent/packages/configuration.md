# univeros/configuration  ·  Altair\Configuration

**Purpose:** The Altair Configuration package.

## Public contracts

| Interface | Method | Returns | Notes |
|---|---|---|---|
| `ConfigurationInterface` | `apply(Container)` | `void` |  |

## Concrete classes

- `ConfigurationCollection` — implements `ArrayAccess`, `CapacityInterface`, `CollectionInterface`, `ConfigurationInterface`, `Countable`, `IteratorAggregate`, `JsonSerializable`, `SetInterface`, `Stringable`, `Traversable`
- `Env`
- `EnvironmentConfiguration` — implements `ConfigurationInterface`

## Tests as documentation

- `tests/Configuration/ConfigurationCollectionTest.php`
- `tests/Configuration/EnvironmentConfigurationTest.php`

## Related packages

- `univeros/container`
- `univeros/structure`
- `vlucas/phpdotenv`
