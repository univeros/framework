# Events

> An append-only mutation event log at `.altair/events.jsonl`, so an agent or a human can answer "what just changed?" across sessions — every scaffold, migration, rewind, rector run, or worker consume leaves one line behind.

**Composer:** `univeros/events`
**Namespace:** `Altair\Events`

## Introduction

When you (or an agent acting on your behalf) run a sequence of mutating commands — scaffold three endpoints, apply a migration, rewind one of them, run `cs:fix` — the working tree tells you the *current* state, but it loses the *story*. Git history only captures what you committed; it says nothing about the dozen iterations between commits, and it certainly doesn't survive a fresh agent session that starts with an empty context window. This package fills that gap. Every mutating framework operation appends one JSON line to `.altair/events.jsonl`, and a small read/query layer lets you ask "what happened since my last good state?" without re-reading the whole tree.

The format is deliberately humble: newline-delimited JSON, flat fields, `jq`-friendly. The log is local-first and trusted-but-careful — it lives under `.altair/` next to your checkout, never leaves the machine, and a [Scrubber](#scrubber-secret-redaction) strips secret-bearing flags before anything is written. Recording is *best-effort*: if the disk is full or the filesystem is read-only, the event drops silently and your command still returns the value it was going to return. The log is observability, never load-bearing logic.

This is **not** a PSR-14 event dispatcher. If you want to publish an in-process domain event (`order.shipped`) and have listeners react to it within the same request, reach for [Happen](./happen.md) — that package owns synchronous, listener-driven dispatch. This package (`univeros/events`) owns the *persistent, append-only record of mutations* that already happened. The two share the word "event" and nothing else: Happen is fire-and-observe inside one process; Events is write-and-recall across many sessions. Keep them straight by asking what you need — to *react* to something now (Happen) or to *remember* something later (Events).

## Installation

```bash
composer require univeros/events
```

The runtime dependencies are `psr/log ^3.0` (warnings when recording fails), `symfony/uid ^7.0` (the ULID that stamps every event), and the framework's `univeros/cli`, `univeros/configuration`, and `univeros/container` packages — the CLI commands plug into attribute-driven command discovery, and `EventsConfiguration` wires everything from environment variables. No database, no extension.

## Quick start

The log fills itself as you use the framework. The commands you reach for most read it back. To see the newest events first, tail the log — the `-n` flag controls how many lines you get back:

```bash
bin/altair events:tail -n 50
```

When you start a fresh session and want to know what changed since the last time everything was green, ask for events since the most recent successful one. This walks newest-first and stops at the first `ok` event, so you see exactly the run of failures/partials that followed your last good state:

```bash
bin/altair events:since-last-success
```

To narrow the log to one kind of operation, or only the failures, filter by kind and/or status — both flags take comma-separated values and default to "all":

```bash
bin/altair events:filter --kind=scaffold,migration --status=fail
```

For a bird's-eye view — totals by kind, by status, and the cumulative wall-clock time the framework has spent mutating this checkout — print the aggregate stats:

```bash
bin/altair events:stats
```

Every read command also accepts `--format=json` when you want to pipe the output into `jq` or hand it to an agent instead of reading it yourself.

## Concepts

### The Event record

An `Event` is a `final readonly` value object — one immutable line in the log. You almost never construct one directly; you use the named-constructor factory `Event::create()`, which stamps a fresh ULID and the current UTC instant for you so identity and ordering stay consistent:

```php
public static function create(
    Actor $actor,
    string $command,
    EventKind $kind,
    EventStatus $status,
    int $durationMs,
    ?string $user = null,
    ?string $client = null,
    ?Changes $changes = null,
    ?string $error = null,
    array $extra = [],
): self;
```

The constructor validates at the boundary: the `id` and `command` must be non-empty, `durationMs` must be non-negative, and a `Fail` status *must* carry a non-empty `error` string — a failed event with no explanation is a contradiction the constructor refuses to build. `Event::toArray()` and `Event::toJsonLine()` serialise it; `Event::fromArray()` hydrates it back (tolerating timestamps with or without microseconds). The ULID gives you sortable-by-creation identity for free, which is why `events:since <id>` works.

### EventKind, EventStatus, Actor

Three string-backed enums classify each event. `EventKind` is the catalogue of mutating operations the framework records — kept alphabetical so the enum diffs cleanly:

```
CsFix = 'cs_fix'                       ManualEdit = 'manual_edit'
Eval = 'eval'                          Migration = 'migration'
IndexBuild = 'index_build'            RectorRun = 'rector_run'
ManifestGenerate = 'manifest_generate' Replay = 'replay'
                                       Rewind = 'rewind'
                                       Scaffold = 'scaffold'
                                       WorkerConsume = 'worker_consume'
```

`EventStatus` is the three-valued outcome: `Ok = 'ok'`, `Fail = 'fail'`, `Partial = 'partial'`. `Partial` is for the half-succeeded case — a batch scaffold that wrote four files and choked on the fifth. `Actor` records *who* triggered the event: `Cli = 'cli'` (a `bin/altair` invocation by a human or agent shell), `Mcp = 'mcp'` (an MCP client like Claude Desktop invoking a framework tool), `Worker = 'worker'` (a long-running consumer), and `Script = 'script'` (a one-off PHP script). Use `EventKind::fromString()` when reading untrusted input — it throws on an unknown value rather than returning null.

### Changes — the "what changed" payload

`Changes` is an immutable map of verb buckets plus an optional snapshot reference. The vocabulary is open-ended on purpose: a scaffold names buckets like `created` and `modified`, a migration names `applied`, a rewind names `restored` — the type doesn't lock you into one set of words. You build it up immutably with `withBucket()` and `withSnapshotRef()`, each returning a new copy:

```php
use Altair\Events\Changes;

$changes = (new Changes())
    ->withBucket('created', 'src/App/User.php', 'src/App/UserRepository.php')
    ->withBucket('modified', 'config/routes.php');
```

When the change set is too large to inline on one line — a 200-file rector run — write the heavy diff to a snapshot and reference it (`->withSnapshotRef('snapshots/<id>.json')`) instead of bloating the log line.

### Best-effort recording

`RecorderInterface` has one method, `record(Event $event): void`, and its contract is the important part: **implementations MUST NOT throw.** The default `Recorder` pipes the event through the `Scrubber`, hands it to storage, and swallows any storage failure (logging it at warning level through an injected PSR-3 logger). The consequence is liberating — you can call `record()` from inside any command without wrapping it in a try/catch or worrying that a read-only filesystem will break the command's real work. `NullRecorder` is the no-op binding used when recording is switched off.

### Scrubber — secret redaction

Command lines routinely carry secrets by accident — a copy-pasted `--password`, a `--token` flag. The `Scrubber` redacts them to `***` before the command string is persisted, so "tail the log to debug" never becomes the way credentials leak. It recognises a default list (`--password`, `--passwd`, `--pass`, `--token`, `--api-key`, `--secret`, `--bearer`, `--access-token`, `--db-password`, and more) in both `--flag=value` and `--flag value` forms, case-insensitively on the flag name. Add your own via `withSecrets()` or the `ALTAIR_EVENTS_EXTRA_SECRET_FLAGS` env var (see [Configuration](#configuration)).

### Storage: JSONL, snapshots, checkpoints

Three storage classes back the log, each with a single responsibility:

- **`JsonlStorage`** is the main append-only file. Writes take an exclusive advisory lock (`flock LOCK_EX`) so concurrent `bin/altair` processes don't tear each other's lines; reads take no lock and simply skip any line that fails to JSON-decode. The parent `.altair/` directory is created on demand, so you never have to provision it.
- **`SnapshotStorage`** holds the oversized change sets — one atomically-written (`tmp` + `rename`) JSON file per event at `.altair/snapshots/<event_id>.json`, referenced from the log line via `changes.snapshot_ref`.
- **`CheckpointStorage`** holds named bookmarks at `.altair/checkpoints/<name>.json`, each pointing at the event id that was the head of the stream when you created it. Names are filesystem-safe (alphanumeric plus `_ . - /`, no `..`).

`JsonlStorage` implements `EventStorageInterface` (`append`, `readAll` oldest→newest, `readReverse` newest→oldest, `count`); the `Reader` queries on top of it.

## Usage

### Recording an event from your own mutating command

If you write a new command that mutates the checkout, record an event so it joins the same log. Type-hint `RecorderInterface` in your constructor — the container hands you either the real `Recorder` or a `NullRecorder` depending on configuration, so you never branch on whether recording is enabled. Time the work, build a `Changes` payload describing what moved, and call `record(Event::create(...))` once on the way out:

```php
<?php

declare(strict_types=1);

namespace App\Command;

use Altair\Events\Actor;
use Altair\Events\Changes;
use Altair\Events\Contracts\RecorderInterface;
use Altair\Events\Event;
use Altair\Events\EventKind;
use Altair\Events\EventStatus;

final readonly class ImportFixturesCommand
{
    public function __construct(
        private RecorderInterface $recorder,
    ) {}

    public function __invoke(): int
    {
        $start = hrtime(true);

        try {
            $written = $this->doImport(); // returns list<string> of touched paths

            // Success: stamp a fresh ULID + UTC instant via the factory.
            $this->recorder->record(Event::create(
                actor: Actor::Cli,
                command: 'app:import-fixtures',
                kind: EventKind::Scaffold,
                status: EventStatus::Ok,
                durationMs: $this->elapsedMs($start),
                changes: (new Changes())->withBucket('created', ...$written),
            ));

            return 0;
        } catch (\Throwable $e) {
            // A Fail event MUST carry a non-empty error — the constructor enforces it.
            $this->recorder->record(Event::create(
                actor: Actor::Cli,
                command: 'app:import-fixtures',
                kind: EventKind::Scaffold,
                status: EventStatus::Fail,
                durationMs: $this->elapsedMs($start),
                error: $e->getMessage(),
            ));

            return 1;
        }
    }

    private function elapsedMs(float $start): int
    {
        return (int) ((hrtime(true) - $start) / 1_000_000);
    }
}
```

Note the single `record()` call per outcome and the fact that you never guard it — best-effort recording means a storage failure won't sabotage your import. Always go through `Event::create()` so the ULID and timestamp stamping stays consistent; never hand-build the JSON line.

### Reading the log programmatically

The `Reader` is the query layer over storage. Inject it (it's bound as a shared service) and call the projection you need — every method returns a generator so a large log streams rather than loading whole:

```php
use Altair\Events\Reader;
use Altair\Events\EventKind;
use Altair\Events\EventStatus;

final readonly class DiagnosticsService
{
    public function __construct(private Reader $reader) {}

    public function recentFailures(): array
    {
        // Newest-first, only failed migrations.
        return iterator_to_array(
            $this->reader->filter([EventKind::Migration], [EventStatus::Fail]),
            false,
        );
    }
}
```

`Reader` also exposes `tail($n)`, `since($threshold)`, `sinceId($eventId)`, `sinceLastSuccess()`, `findById($eventId)`, and `stats()` — the same projections the CLI commands sit on top of.

### Checkpoints — bookmarking the stream

Before you start a risky multi-step run, bookmark the current head. Later, ask what happened since the bookmark:

```bash
bin/altair events:checkpoint:create feat/posts   # bookmark current head
# ... do work: scaffold, migrate, edit ...
bin/altair events:checkpoint:diff feat/posts     # events recorded after the bookmark
bin/altair events:checkpoint:list                # all stored checkpoints
bin/altair events:checkpoint:delete feat/posts   # remove one
```

A checkpoint stores only the event id of the head at creation time, so `checkpoint:diff` is just `sinceId` under the hood.

### Compaction — keeping the log small

The log is meant to be compacted before it gets gigantic. Archive everything older than a cutoff into `.altair/events.archive/`, leaving the active log lean:

```bash
bin/altair events:compact --before=2026-04-01
```

## Configuration

`EventsConfiguration` wires every primitive into the container from environment variables. It parses `EventsSettings` once, then binds `Scrubber`, `JsonlStorage` (aliased to `EventStorageInterface`), `SnapshotStorage`, `CheckpointStorage`, `Reader`, and `RecorderInterface` as shared services. The one branch that matters: when `ALTAIR_EVENTS_ENABLED=false`, `RecorderInterface` resolves to `NullRecorder` instead of `Recorder` — every other binding is unconditional.

```php
use Altair\Events\Configuration\EventsConfiguration;

// projectRoot defaults to the current working directory when omitted.
$configuration = new EventsConfiguration(projectRoot: __DIR__);
$configuration->apply($container);
```

The settings come from these variables (parsed in `EventsSettings::fromEnv`):

| Variable | Default | Purpose |
|---|---|---|
| `ALTAIR_EVENTS_ENABLED` | `true` | Set `false` to bind `NullRecorder` and stop persisting. |
| `ALTAIR_EVENTS_DIR` | `.altair` | Base directory, relative to the project root. |
| `ALTAIR_EVENTS_LOG_FILE` | `events.jsonl` | Log filename inside the base directory. |
| `ALTAIR_EVENTS_SNAPSHOTS_DIR` | `snapshots` | Snapshot subdirectory. |
| `ALTAIR_EVENTS_CHECKPOINTS_DIR` | `checkpoints` | Checkpoints subdirectory. |
| `ALTAIR_EVENTS_EXTRA_SECRET_FLAGS` | (empty) | Comma-separated extra flag names to redact, beyond the Scrubber defaults. |

`ALTAIR_EVENTS_ENABLED` reads truthily: `0`, `false`, `off`, `no`, and empty disable it; anything else enables it. Set `ALTAIR_EVENTS_DIR` to relocate the whole `.altair/` tree — useful in a read-only image build, where you'd point it at a writable tmp path or simply disable recording.

## Testing

The tests under `tests/Events/` document the contract component by component:

- `tests/Events/EventTest.php` and `tests/Events/ChangesTest.php` — the value objects: factory stamping, validation rejections (empty id/command, negative duration, errorless `Fail`), and the `toArray`/`fromArray` round-trip.
- `tests/Events/RecorderTest.php` — that the `Recorder` scrubs secrets before storage and that a throwing storage layer never propagates out of `record()`.
- `tests/Events/ReaderTest.php` — every projection (`tail`, `since`, `sinceId`, `sinceLastSuccess`, `filter`, `stats`).
- `tests/Events/ScrubberTest.php` — both flag forms, case-insensitivity, and custom-secret extension.
- `tests/Events/Storage/` — `JsonlStorageTest`, `SnapshotStorageTest`, `CheckpointStorageTest`: append/read/atomicity per medium.
- `tests/Events/Integration/ConcurrentWriteTest.php` — that the `flock` guard keeps interleaved writers from tearing lines.
- `tests/Events/Cli/CommandsTest.php` and `tests/Events/Configuration/` — the CLI surface and the env-to-container wiring.

When you add a recording call to a new command, assert it with a spy: bind a fake `RecorderInterface` that captures the `Event` it receives, run the command, and assert the captured event's `kind`, `status`, and `changes`. Because the real `Recorder` is best-effort, a spy is the only reliable way to prove your command records what you think it does.

## Related packages

- [scaffold.md](./scaffold.md) — the scaffolder writes a `scaffold` event on every successful `spec:scaffold`, and its own journal sub-feature (`journal:rewind`/`journal:replay`) is the heavier, content-restoring sibling of this log. The two are complementary: the journal can undo a scaffold; this log records that one happened.
- [cli.md](./cli.md) — the attribute-driven CLI substrate. Every `events:*` command is a plain invokable registered through `Altair\Cli\Attribute\Command`, with options declared via `#[Option]` and `#[Argument]`.
- [mcp.md](./mcp.md) — the MCP server exposes the same Reader projections as tools, which is why `Actor::Mcp` exists: events recorded through an MCP client are tagged distinctly from CLI ones.
- [happen.md](./happen.md) — the PSR-14 in-process event **dispatcher**. Do not confuse it with this package: Happen is for *reacting* to a notification synchronously within one request; Events is for *remembering* a mutation persistently across sessions. Different problems, no shared API.

## Limitations

- **Local-only.** The log lives under `.altair/` next to your checkout and never leaves the machine. There is no remote aggregation, no shared store — if you want a team-wide audit trail, ship the lines somewhere yourself.
- **Keep `.altair/` gitignored.** Events are per-checkout local state, not source. Committing the log creates merge conflicts and leaks machine-specific noise into history; add `.altair/` to the host application's `.gitignore`.
- **Recording is best-effort.** A failed `record()` drops the event silently (logged at warning level, if you injected a logger). Never build logic that depends on a particular event having been written — the log is observability, not a transactional ledger.
- **Reads are unlocked and skip-tolerant.** A torn concurrent write surfaces as one line that fails to decode; the Reader skips it rather than aborting. That's the right trade-off for a best-effort log, but it means line-level loss is possible under pathological concurrency.
- **Compact before it grows.** `readReverse` loads the whole file into memory by design. The log is meant to be compacted (`events:compact`) periodically; an uncompacted multi-gigabyte log will degrade the newest-first commands.
