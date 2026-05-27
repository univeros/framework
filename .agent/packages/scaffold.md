# univeros/scaffold  ·  Altair\Scaffold

**Purpose:** Spec-to-API code generator: YAML spec in, Action/Input/Responder + OpenAPI + tests out. Includes a **journal** sub-feature for rewindable/replayable scaffold operations.

## Journal sub-feature (issue #72)

Every successful `bin/altair spec:scaffold` writes a self-contained entry to `.altair/journal/<timestamp>-<short-sha>.json` capturing the spec content, per-file SHAs, and full `content_before` for modified files. The journal turns failed iterations into recoverable ones rather than catastrophic ones.

CLI commands (named `journal:*` to avoid collision with introspection's `spec:*`):

| Command | Purpose |
|---|---|
| `journal:list [-n N] [--since=<ts>] [--spec=<path>]` | List entries newest-first. |
| `journal:show <id>` | Full detail for one entry (resolves unambiguous id prefixes). |
| `journal:diff <id>` | Per-file diffs embedded in one entry. |
| `journal:rewind [--to=<id>] [--dry-run] [--force]` | Undo scaffold operations newest-first; refuses to clobber hand-edited files unless `--force`. |
| `journal:replay [--from=<id>\|--all] [<id>]` | Re-emit one or more entries from embedded spec content; reports drift. |

All accept `--format=json` where applicable.

### Integration with scaffold + events

`ScaffoldCommand` takes an optional `Journal` and an optional `Altair\Events\Contracts\RecorderInterface`. When both are bound (via `ScaffoldJournalConfiguration` + `EventsConfiguration`), every scaffold writes a journal entry **and** emits a `scaffold` event into `.altair/events.jsonl`. Both integrations are nullable so minimal hosts can scaffold without either.

### Configuration

```php
use Altair\Scaffold\Journal\Configuration\ScaffoldJournalConfiguration;

(new ScaffoldJournalConfiguration(projectRoot: __DIR__))->apply($container);
```

Env vars: `ALTAIR_JOURNAL_ENABLED` (default true), `ALTAIR_JOURNAL_DIR` (`.altair`), `ALTAIR_JOURNAL_SUBDIR` (`journal`).

## Concrete classes

- `ActionEmitter`
- `DomainSpec` _(final)_
- `DomainStubEmitter`
- `DriftDetector`
- `DriftFinding` _(final)_
- `DriftKind` _(final)_ — implements `BackedEnum`, `UnitEnum`
- `DriftReport` _(final)_
- `EmissionPlan`
- `EmitOpenApiCommand` _(final)_
- `EmittedFile` _(final)_
- `EmittedFileKind` _(final)_ — implements `BackedEnum`, `UnitEnum`
- `EndpointSpec` _(final)_
- `EntityEmitter` _(final)_
- `FileWriter`
- `HandlerEmitter`
- `HandlerTestEmitter`
- `InputEmitter`
- `InputFieldSpec` _(final)_
- `LintCommand` _(final)_
- `MessageEmitter`
- `MigrationEmitter` _(final)_
- `Naming` _(final)_
- `OpenApiEmitter`
- `OutputResponseSpec` _(final)_
- `Parser`
- `PathResolver`
- `PersistenceEntitySpec` _(final)_
- `PersistenceFieldSpec` _(final)_
- `PersistenceSpec` _(final)_
- `PhpHeader` _(final)_
- `QueueDispatchSpec` _(final)_
- `RepositoryEmitter` _(final)_
- `ResponderEmitter`
- `RouteEmitter`
- `ScaffoldCommand` _(final)_
- `Spec` _(final)_
- `SpecLoader`
- `TestEmitter`
- `TypeMapper` _(final)_
- `Validator`
- `WriteOutcome` _(final)_
- `WriteStatus` _(final)_ — implements `BackedEnum`, `UnitEnum`

## Tests as documentation

- `tests/Scaffold/Cli/ScaffoldCommandIntegrationTest.php`
- `tests/Scaffold/Emitter/ActionEmitterTest.php`
- `tests/Scaffold/Emitter/DomainStubEmitterTest.php`
- `tests/Scaffold/Emitter/EntityEmitterTest.php`
- `tests/Scaffold/Emitter/HandlerEmitterTest.php`
- `tests/Scaffold/Emitter/HandlerTestEmitterTest.php`
- `tests/Scaffold/Emitter/InputEmitterTest.php`
- `tests/Scaffold/Emitter/MessageEmitterTest.php`
- `tests/Scaffold/Emitter/MigrationEmitterTest.php`
- `tests/Scaffold/Emitter/OpenApiEmitterTest.php`
- `tests/Scaffold/Emitter/RepositoryEmitterTest.php`
- `tests/Scaffold/Emitter/ResponderEmitterTest.php`
- `tests/Scaffold/Emitter/RouteEmitterTest.php`
- `tests/Scaffold/Emitter/TestEmitterTest.php`
- `tests/Scaffold/Linter/DriftDetectorTest.php`
- `tests/Scaffold/Spec/ParserTest.php`
- `tests/Scaffold/Spec/PersistenceParserTest.php`
- `tests/Scaffold/Spec/PersistenceValidatorTest.php`
- `tests/Scaffold/Spec/ValidatorTest.php`

## Related packages

- `nikic/php-parser`
- `symfony/yaml`
- `univeros/cli`
- `univeros/configuration` (Journal Configuration)
- `univeros/container` (Journal Configuration)
- `univeros/events` (suggested — for event-log dual-write from `ScaffoldCommand`)

## Issue references

- #19 — `univeros/scaffold` itself (shipped)
- #72 — spec journal (rewind / replay) — this PR
