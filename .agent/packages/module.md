# univeros/module  ·  Altair\Module

**Purpose:** Pluggable extension modules for Univeros: a module self-registers its routes, Cycle entities, and migrations into a host app via one container tag.

## Public contracts

| Interface | Method | Returns | Notes |
|---|---|---|---|
| `EntityDirectoriesProviderInterface` | `entityDirectories()` | `array` |  |
| `MigrationDirectoriesProviderInterface` | `migrationDirectories()` | `array` |  |
| `ModuleInterface` | `name()` | `string` | extends `ConfigurationInterface` |
| `RoutesProviderInterface` | `routes()` | `array` |  |

## Concrete classes

- `MigrationSource` _(final)_
- `ModuleConfiguration` _(final)_ — implements `ConfigurationInterface`

## Tests as documentation

- `tests/Module/ModuleConfigurationTest.php`

## Related packages

- `univeros/configuration`
- `univeros/container`
