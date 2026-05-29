# univeros/observatory  ·  Altair\Observatory

**Purpose:** Observatory — a dev-only web monitoring panel for Altair apps. A thin, gated presentation layer over the framework's introspection, doctor, events, messaging and persistence data sources.

## Public contracts

| Interface | Method | Returns | Notes |
|---|---|---|---|
| `FailedQueueReaderInterface` | `failedCount()` | `int` |  |
|  | `recentFailures(int)` | `array` |  |
|  | `transportNames()` | `array` |  |
| `MigrationStatusReaderInterface` | `read()` | `array\|null` |  |
| `PanelInterface` | `icon()` | `string` |  |
|  | `id()` | `string` |  |
|  | `label()` | `string` |  |
|  | `snapshot()` | `PanelSnapshot` |  |

## Concrete classes

- `ActivityStreamHandler` _(final)_ — implements `RequestHandlerInterface`
- `ConfigPanel` _(final)_ — implements `PanelInterface`
- `ContainerPanel` _(final)_ — implements `PanelInterface`
- `DashboardHandler` _(final)_ — implements `RequestHandlerInterface`
- `EnvironmentAccessGuard` _(final)_ — implements `AccessGuardInterface`
- `EventsPanel` _(final)_ — implements `PanelInterface`
- `HealthPanel` _(final)_ — implements `PanelInterface`
- `IconSet` _(final)_
- `MigrationStatus` _(final)_
- `MigrationsPanel` _(final)_ — implements `PanelInterface`
- `Observatory` _(final)_
- `ObservatoryConfiguration` — implements `ConfigurationInterface`
- `PanelRegistry` _(final)_
- `PanelSnapshot` _(final)_
- `PanelStatus` _(final)_ — implements `BackedEnum`, `UnitEnum`
- `QueuesPanel` _(final)_ — implements `PanelInterface`
- `RoutesPanel` _(final)_ — implements `PanelInterface`
- `RuntimePanel` _(final)_ — implements `PanelInterface`
- `TemplateRenderer` _(final)_

## Tests as documentation

- `tests/Observatory/EnvironmentAccessGuardTest.php`
- `tests/Observatory/Http/ActivityStreamHandlerTest.php`
- `tests/Observatory/Http/DashboardHandlerTest.php`
- `tests/Observatory/ObservatoryTest.php`
- `tests/Observatory/Panel/ConfigPanelTest.php`
- `tests/Observatory/Panel/ContainerPanelTest.php`
- `tests/Observatory/Panel/EventsPanelTest.php`
- `tests/Observatory/Panel/HealthPanelTest.php`
- `tests/Observatory/Panel/MigrationsPanelTest.php`
- `tests/Observatory/Panel/QueuesPanelTest.php`
- `tests/Observatory/Panel/RoutesPanelTest.php`
- `tests/Observatory/PanelRegistryTest.php`
- `tests/Observatory/PanelSnapshotTest.php`
- `tests/Observatory/RuntimePanelTest.php`

## Related packages

- `psr/http-factory`
- `psr/http-message`
- `psr/http-server-handler`
- `univeros/configuration`
- `univeros/container`
- `univeros/doctor`
- `univeros/events`
- `univeros/introspection`
