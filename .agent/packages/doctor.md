# univeros/doctor  ·  Altair\Doctor

**Purpose:** bin/altair doctor — health checks with agent-actionable output. Deterministic JSON for agents, scannable text for humans.

## Public contracts

| Interface | Method | Returns | Notes |
|---|---|---|---|
| `CheckInterface` | `dependsOn()` | `array` |  |
|  | `name()` | `string` |  |
|  | `run()` | `CheckResult` |  |
| `FixableCheckInterface` | `fix()` | `bool` | extends `CheckInterface` |
| `ProcessRunnerInterface` | `run(array, string\|null)` | `ProcessResult` |  |
| `ReportRendererInterface` | `render(Report)` | `string` |  |

## Concrete classes

- `AgentAction` _(final)_
- `CheckRegistry` _(final)_
- `CheckResult` _(final)_
- `CheckStatus` _(final)_ — implements `BackedEnum`, `UnitEnum`
- `ComposerDepsCheck` _(final)_ — implements `CheckInterface`, `FixableCheckInterface`
- `ContainerBootsCheck` _(final)_ — implements `CheckInterface`
- `ContainerResolvesCheck` _(final)_ — implements `CheckInterface`
- `CsCleanCheck` _(final)_ — implements `CheckInterface`, `FixableCheckInterface`
- `DatabaseReachableCheck` _(final)_ — implements `CheckInterface`
- `DeterminismCheck` _(final)_ — implements `CheckInterface`
- `Doctor` _(final)_
- `DoctorCommand` _(final)_
- `DoctorConfiguration` _(final)_ — implements `ConfigurationInterface`
- `ExtensionsLoadedCheck` _(final)_ — implements `CheckInterface`
- `HumanRenderer` _(final)_ — implements `ReportRendererInterface`
- `JsonRenderer` _(final)_ — implements `ReportRendererInterface`
- `ManifestsCurrentCheck` _(final)_ — implements `CheckInterface`, `FixableCheckInterface`
- `MigrationsPendingCheck` _(final)_ — implements `CheckInterface`, `FixableCheckInterface`
- `OpenApiValidCheck` _(final)_ — implements `CheckInterface`
- `PhpVersionCheck` _(final)_ — implements `CheckInterface`
- `PhpstanCleanCheck` _(final)_ — implements `CheckInterface`
- `ProcessResult` _(final)_
- `RendererRegistry` _(final)_
- `Report` _(final)_
- `ShellProcessRunner` _(final)_ — implements `ProcessRunnerInterface`
- `SpecDriftCheck` _(final)_ — implements `CheckInterface`
- `TestsPassingCheck` _(final)_ — implements `CheckInterface`

## Tests as documentation

- `tests/Doctor/Check/HostAppChecksTest.php`
- `tests/Doctor/Check/ProcessChecksTest.php`
- `tests/Doctor/Check/PureChecksTest.php`
- `tests/Doctor/Cli/DoctorCommandTest.php`
- `tests/Doctor/Configuration/DoctorConfigurationTest.php`
- `tests/Doctor/DoctorTest.php`
- `tests/Doctor/Output/RenderersTest.php`
- `tests/Doctor/Result/ResultObjectsTest.php`

## Related packages

- `univeros/cli`
- `univeros/configuration`
- `univeros/container`
