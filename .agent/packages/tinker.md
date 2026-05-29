# univeros/tinker  ·  Altair\Tinker

**Purpose:** bin/altair tinker — an interactive PsySH REPL with the DI container in scope and a doctor-style preamble of what's wired. A local debugging tool for developers, not an agent surface.

## Public contracts

| Interface | Method | Returns | Notes |
|---|---|---|---|
| `ReplInterface` | `isAvailable()` | `bool` |  |
|  | `run(ReplContext, string)` | `int` |  |

## Concrete classes

- `ContainerCaster` _(final)_
- `PreambleBuilder` _(final)_
- `PsyConfigurationFactory` _(final)_
- `PsyShellRepl` _(final)_ — implements `ReplInterface`
- `ReplContext` _(final)_
- `TinkerCommand` _(final)_
- `TinkerConfiguration` _(final)_ — implements `ConfigurationInterface`

## Tests as documentation

- `tests/Tinker/Cli/TinkerCommandTest.php`
- `tests/Tinker/Configuration/TinkerConfigurationTest.php`
- `tests/Tinker/Preamble/PreambleBuilderTest.php`
- `tests/Tinker/Repl/ContainerCasterTest.php`
- `tests/Tinker/Repl/PsyConfigurationFactoryTest.php`
- `tests/Tinker/Repl/PsyShellReplTest.php`
- `tests/Tinker/Repl/ReplContextTest.php`

## Related packages

- `psy/psysh`
- `univeros/cli`
- `univeros/configuration`
- `univeros/container`
