---
title: Record an event and read it back through the Reader
scenario: Capture a structured mutation in the agent-readable event log, then assert against it from a test or follow-up step.
packages: [events]
since: 2.0.0
tested_by: tests/Examples/EventsRecorderRoundtripTest.php
---

# Record an event and read it back through the Reader

`Altair\Events\Recorder::record()` writes a structured entry into `.altair/events.jsonl`. `Altair\Events\Reader` reads it back. Together they are the cross-session memory that an agent (or a test) uses to answer "what just happened?"

```php
use Altair\Events\Actor;
use Altair\Events\Event;
use Altair\Events\EventKind;
use Altair\Events\EventStatus;
use Altair\Events\Reader;
use Altair\Events\Recorder;
use Altair\Events\Scrubber;
use Altair\Events\Storage\JsonlStorage;

$storage = new JsonlStorage('/tmp/example-events.jsonl');
$recorder = new Recorder($storage, new Scrubber());
$reader = new Reader($storage);

$recorder->record(Event::create(
    actor: Actor::Cli,
    command: 'bin/altair example:command',
    kind: EventKind::ManualEdit,
    status: EventStatus::Ok,
    durationMs: 42,
));

foreach ($reader->all() as $event) {
    // $event->command   === 'bin/altair example:command'
    // $event->status    === EventStatus::Ok
    // $event->durationMs === 42
}
```

## Gotchas

- **The `Scrubber` redacts known secret flags** (`--password=`, `--token=`, `--api-key=`, …) before the event is persisted. You almost always want it. Pass `(new Scrubber())->withSecrets(['--my-extra'])` to extend the list.
- **`Recorder::record()` is best-effort** — it swallows storage failures so a CLI command stays correct even when the log can't be written. Tail it via `bin/altair events:tail` to check it is actually landing.
- **Use `Event::create()` named-constructor**, not `new Event(...)` — the constructor signature is private-by-convention because it stamps the ULID and timestamp for you.
- **`durationMs` is wall-clock**, not CPU. Always pass an explicit measurement; the framework does not add one for you.
