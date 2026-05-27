# univeros/events  ·  Altair\Events

**Purpose:** Append-only mutation event log (`.altair/events.jsonl`) — session memory for agents and humans. Records every mutating framework operation (scaffold, migration, rewind, replay, eval-with-writes, cs:fix, rector, worker consume, manifest generate) so an agent can reason about "what changed since I last knew the world?"

## Public contracts

| Interface | Method | Returns | Notes |
|---|---|---|---|
| `RecorderInterface` | `record(Event)` | `void` | Best-effort — never throws; failures are logged and dropped. |
| `EventStorageInterface` | `append(Event)` | `void` | MUST be atomic against concurrent writers. |
| `EventStorageInterface` | `readAll()` | `iterable<Event>` | Oldest first. |
| `EventStorageInterface` | `readReverse()` | `iterable<Event>` | Newest first. |
| `EventStorageInterface` | `count()` | `int` | Cheap line count. |

## Concrete classes

- `Event` _(final readonly)_ — immutable value object; JSON-line shape
- `EventKind` _(enum: string)_ — scaffold, migration, rewind, replay, eval, cs_fix, rector_run, manifest_generate, index_build, worker_consume, manual_edit
- `EventStatus` _(enum: string)_ — ok, fail, partial
- `Actor` _(enum: string)_ — cli, mcp, worker, script
- `Changes` _(final readonly)_ — buckets keyed by verb (`created`, `modified`, `applied`, ...) + optional `snapshot_ref`
- `Recorder` _(final readonly)_ — implements `RecorderInterface`; pipes events through the Scrubber before storage
- `NullRecorder` _(final readonly)_ — no-op binding when `ALTAIR_EVENTS_ENABLED=false`
- `Reader` _(final readonly)_ — tail / since / since-id / since-last-success / filter / stats
- `Scrubber` _(final readonly)_ — redacts known secret flags before persistence
- `Storage\JsonlStorage` _(final readonly)_ — file-locked append; tolerant of malformed lines on read
- `Storage\SnapshotStorage` _(final readonly)_ — sidecar JSON blobs for large changesets
- `Storage\CheckpointStorage` _(final readonly)_ — named bookmarks
- `Configuration\EventsConfiguration` _(final readonly)_ — implements `ConfigurationInterface`
- `Configuration\EventsSettings` _(final readonly)_

## CLI commands

| Command | Purpose |
|---|---|
| `events:tail` | Print the last N events (newest first). |
| `events:show <id>` | Full detail for one event, plus its snapshot blob if present. |
| `events:since <when>` | Events strictly after a timestamp or event ID. |
| `events:since-last-success` | Events recorded after the most recent OK event. |
| `events:filter --kind=… --status=…` | Filter the log by kind and/or status. |
| `events:stats` | Aggregate counts by kind/status + total wall time. |
| `events:checkpoint:create <name>` | Bookmark the current head of the event stream. |
| `events:checkpoint:list` | List stored checkpoints. |
| `events:checkpoint:delete <name>` | Delete a checkpoint. |
| `events:checkpoint:diff <name>` | Events recorded since the named checkpoint. |
| `events:compact --before=<ts>` | Archive old events into `.altair/events.archive/YYYY-MM.jsonl.gz`. |

All commands accept `--format=json` for agent/MCP consumption.

## Configuration (env vars)

| Variable | Default | Purpose |
|---|---|---|
| `ALTAIR_EVENTS_ENABLED` | `true` | Set `false` to bind `NullRecorder`. |
| `ALTAIR_EVENTS_DIR` | `.altair` | Base directory (relative to project root). |
| `ALTAIR_EVENTS_LOG_FILE` | `events.jsonl` | Log filename inside the base directory. |
| `ALTAIR_EVENTS_SNAPSHOTS_DIR` | `snapshots` | Snapshot subdirectory. |
| `ALTAIR_EVENTS_CHECKPOINTS_DIR` | `checkpoints` | Checkpoints subdirectory. |
| `ALTAIR_EVENTS_EXTRA_SECRET_FLAGS` | _(empty)_ | Comma-separated extra flag names to redact. |

## Wiring example (host app)

```php
use Altair\Events\Configuration\EventsConfiguration;
use Altair\Events\Contracts\RecorderInterface;
use Altair\Events\Event;
use Altair\Events\Actor;
use Altair\Events\EventKind;
use Altair\Events\EventStatus;

(new EventsConfiguration())->apply($container);

$recorder = $container->make(RecorderInterface::class);
$recorder->record(Event::create(
    actor: Actor::Cli,
    command: 'bin/altair spec:scaffold api/users/create.yaml',
    kind: EventKind::Scaffold,
    status: EventStatus::Ok,
    durationMs: 847,
));
```

## Privacy

- The Scrubber redacts a default list of secret flags (`--password`, `--token`, `--api-key`, ...). Host apps add more via `ALTAIR_EVENTS_EXTRA_SECRET_FLAGS`.
- Snapshots are opt-in per event — large changesets go to `.altair/snapshots/<event_id>.json` rather than the main log.
- Skeleton projects should `.gitignore` `.altair/` (the log is local).

## Tests as documentation

- `tests/Events/EventTest.php`
- `tests/Events/ChangesTest.php`
- `tests/Events/ScrubberTest.php`
- `tests/Events/RecorderTest.php`
- `tests/Events/ReaderTest.php`
- `tests/Events/Cli/CommandsTest.php`
- `tests/Events/Configuration/EventsSettingsTest.php`
- `tests/Events/Configuration/EventsConfigurationTest.php`
- `tests/Events/Storage/JsonlStorageTest.php`
- `tests/Events/Storage/SnapshotStorageTest.php`
- `tests/Events/Storage/CheckpointStorageTest.php`
- `tests/Events/Integration/ConcurrentWriteTest.php`

## Related packages

- `psr/log`
- `symfony/uid`
- `univeros/cli`
- `univeros/configuration`
- `univeros/container`

## Issue

[#77 Mutation event log — .altair/events.jsonl for session memory](https://github.com/univeros/framework/issues/77)
