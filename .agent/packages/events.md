# univeros/events  ·  Altair\Events

**Purpose:** Append-only mutation event log (.altair/events.jsonl) — session memory for agents and humans.

## Public contracts

| Interface | Method | Returns | Notes |
|---|---|---|---|
| `EventStorageInterface` | `append(Event)` | `void` |  |
|  | `count()` | `int` |  |
|  | `readAll()` | `iterable` |  |
|  | `readReverse()` | `iterable` |  |
| `RecorderInterface` | `record(Event)` | `void` |  |

## Concrete classes

- `Actor` _(final)_ — implements `BackedEnum`, `UnitEnum`
- `Changes` _(final)_
- `CheckpointCreateCommand` _(final)_
- `CheckpointDeleteCommand` _(final)_
- `CheckpointDiffCommand` _(final)_
- `CheckpointListCommand` _(final)_
- `CheckpointStorage` _(final)_
- `CompactCommand` _(final)_
- `Event` _(final)_
- `EventKind` _(final)_ — implements `BackedEnum`, `UnitEnum`
- `EventRecordingLogger` _(final)_ — implements `LoggerInterface`
- `EventStatus` _(final)_ — implements `BackedEnum`, `UnitEnum`
- `EventsConfiguration` _(final)_ — implements `ConfigurationInterface`
- `EventsSettings` _(final)_
- `FilterCommand` _(final)_
- `JsonlStorage` _(final)_ — implements `EventStorageInterface`
- `NullRecorder` _(final)_ — implements `RecorderInterface`
- `OutputRenderer` _(final)_
- `Reader` _(final)_
- `Recorder` _(final)_ — implements `RecorderInterface`
- `Scrubber` _(final)_
- `ShowCommand` _(final)_
- `SinceCommand` _(final)_
- `SinceLastSuccessCommand` _(final)_
- `SnapshotStorage` _(final)_
- `StatsCommand` _(final)_
- `TailCommand` _(final)_

## Tests as documentation

- `tests/Events/ChangesTest.php`
- `tests/Events/Cli/CommandsTest.php`
- `tests/Events/Configuration/EventsConfigurationTest.php`
- `tests/Events/Configuration/EventsSettingsTest.php`
- `tests/Events/EventRecordingLoggerTest.php`
- `tests/Events/EventTest.php`
- `tests/Events/Integration/ConcurrentWriteTest.php`
- `tests/Events/ReaderTest.php`
- `tests/Events/RecorderTest.php`
- `tests/Events/ScrubberTest.php`
- `tests/Events/Storage/CheckpointStorageTest.php`
- `tests/Events/Storage/JsonlStorageTest.php`
- `tests/Events/Storage/SnapshotStorageTest.php`

## Related packages

- `psr/log`
- `symfony/uid`
- `univeros/cli`
- `univeros/configuration`
- `univeros/container`
