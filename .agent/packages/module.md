# univeros/module  ·  Altair\Module

**Purpose:** Pluggable extension modules for Univeros: a module self-registers its routes, PSR-15 middleware, Cycle entities, and migrations into a host app via one container tag.

## Public contracts

| Interface | Method | Returns | Notes |
|---|---|---|---|
| `EntityDirectoriesProviderInterface` | `entityDirectories()` | `array` |  |
| `MiddlewareProviderInterface` | `middleware()` | `array` |  |
| `MigrationDirectoriesProviderInterface` | `migrationDirectories()` | `array` |  |
| `ModuleInterface` | `name()` | `string` | extends `ConfigurationInterface` |
| `RoutesProviderInterface` | `routes()` | `array` |  |

## Concrete classes

- `MigrationSource` _(final)_
- `ModuleConfiguration` _(final)_ — implements `ConfigurationInterface`

## Tests as documentation

- `tests/Module/ModuleConfigurationTest.php`

## Related packages

- `psr/http-server-middleware`
- `univeros/configuration`
- `univeros/container`
