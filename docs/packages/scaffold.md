# Scaffold

> The spec-driven core of the framework. One YAML endpoint spec goes in; an Action, an Input DTO, a Responder, a domain stub, a PHPUnit test, an OpenAPI 3.1 fragment, and a route entry come out — plus optional entity/repository/migration and message/handler trios. Every scaffold is journaled, so it is reversible. Drift between the spec and the generated code is a CI gate. And the merged OpenAPI document compiles to typed TypeScript and Python client SDKs.

**Composer:** `univeros/scaffold`
**Namespace:** `Altair\Scaffold`

## Introduction

Hand-writing the Action / Input / Responder triple for every HTTP endpoint is the kind of boilerplate that drifts the moment you touch it. You add a field to the Input but forget to add it to the OpenAPI fragment; you change a validation rule but the generated test still asserts the old behaviour; the route never gets registered because you were copy-pasting from the last endpoint and missed a line. Multiply that across a real API and the wire format, the validation, the docs, and the tests all quietly fall out of sync.

This package makes the YAML spec the single source of truth. You describe an endpoint once — its method and path, its inputs and their validation rules, its responses, and the domain service it delegates to — and the scaffolder emits everything downstream from that one description. When you change the spec, you re-run the scaffolder. When someone hand-edits a generated file, `spec:lint` tells you (and CI) exactly where the file and the spec disagree.

Three design choices make this safe to live with rather than a one-shot code dump you immediately abandon:

1. **It is journaled.** Every successful scaffold writes a self-contained `.altair/journal/<id>.json` entry capturing the spec content, per-file SHAs, and the full `content_before` for any file it modified. A bad scaffold is no longer catastrophic — `journal:rewind` undoes it, and it refuses to clobber files you have hand-edited since unless you pass `--force`.
2. **It detects drift.** The linter re-parses the generated PHP with `nikic/php-parser` and compares the AST back to the spec: input fields against constructor params, validation rules against `rules()`, response statuses against `statuses()`, and the action FQCN against the routes file. Drift is reported with an exit code, so it slots straight into CI.
3. **It compiles clients.** The per-endpoint OpenAPI fragments merge into a single OpenAPI 3.1 document, and that document compiles to typed client SDKs — fetch-based TypeScript and `httpx`+`pydantic` Python — with deterministic output you can wire into a `--check` CI gate so the committed SDK can never drift from the spec.

What this package deliberately does *not* do: it does not run your endpoints (that is [http.md](./http.md)), it does not invent a validation engine (rules map onto `Altair\Validation\Rule\*`), and it does not own the ORM or the queue — it only knows enough about [persistence.md](./persistence.md) and [messaging.md](./messaging.md) to emit their artifacts when you ask for them.

## Installation

Standalone:

```bash
composer require --dev univeros/scaffold
```

You almost always want this as a dev dependency: it generates code into your *checkout*, it is not part of your runtime. It pulls in `nikic/php-parser` (the drift linter re-parses emitted PHP), `symfony/yaml` (spec + OpenAPI parsing), and `univeros/cli` (the attribute-driven command runtime that hosts `spec:scaffold` and friends).

If you install the full framework, `composer require univeros/framework` already bundles it.

The journal and the event-log integration are optional. Bind `univeros/events` (the `suggest` in `composer.json`) when you want each scaffold to also append a mutation event to `.altair/events.jsonl` — see [Configuration](#configuration).

## Quick start

The smallest useful spec describes one endpoint and the domain service behind it:

```yaml
# api/users/create.yaml
endpoint: { method: POST, path: /users, summary: Create a user, tags: [users] }
input:
  email: { type: string, rules: [email, required] }
output:
  201: { body: { user: App\User\User } }
domain: { class: App\User\CreateUser }
```

Scaffold it. From the project root:

```bash
bin/altair spec:scaffold api/users/create.yaml
```

That prints one line per file written (or skipped) and emits:

```
written app/Http/Actions/CreateUserAction.php
written app/Http/Inputs/CreateUserInput.php
written app/Http/Responders/CreateUserResponder.php
written app/User/CreateUser.php
written tests/Http/Actions/CreateUserActionTest.php
written docs/openapi/create-user.yaml
modified config/routes.php
Wrote 7 file(s); skipped 0 existing file(s).
```

Preview without touching disk — `--dry-run` prints every planned file's full contents to stdout so you can eyeball the output before committing to it:

```bash
bin/altair spec:scaffold api/users/create.yaml --dry-run
```

Re-runs are idempotent: existing files are *skipped*, not overwritten. When you have intentionally changed the spec and want the generated files regenerated, pass `--force`:

```bash
bin/altair spec:scaffold api/ --force          # batch every spec under api/, overwriting
```

Once you have a few endpoints, merge their OpenAPI fragments into one document and compile clients from it:

```bash
bin/altair spec:emit-openapi --out=docs/openapi.yaml   # merge fragments → one 3.1 doc
bin/altair spec:emit-sdk typescript > sdk.ts           # fetch-based, zero-dep TS client
bin/altair spec:emit-sdk python --out=clients/python   # httpx + pydantic, sync + async
```

And before you push, let the linter catch any place a generated file has drifted from its spec:

```bash
bin/altair spec:lint                                   # exit 1 on drift — CI gate
```

## Concepts

### The spec AST

A spec file is parsed into a small immutable AST under `Altair\Scaffold\Spec\Ast\*`. The root node is `Spec`, a `final readonly` value object:

```php
final readonly class Spec
{
    public function __construct(
        public EndpointSpec $endpoint,
        public array $inputs,            // list<InputFieldSpec>
        public array $outputs,           // list<OutputResponseSpec>
        public DomainSpec $domain,
        public string $sourcePath = '',
        public ?PersistenceSpec $persistence = null,
        public array $queue = [],        // list<QueueDispatchSpec>
    ) {}

    public function artifactName(): string;   // domain short-name, e.g. "CreateUser"
    public function hasPersistence(): bool;
}
```

The pieces map directly onto the YAML blocks:

- **`EndpointSpec`** carries `method`, `path`, `summary`, and `tags`. The method is upper-cased and validated against the supported set (`GET POST PUT PATCH DELETE OPTIONS HEAD`); the path must start with `/`.
- **`InputFieldSpec`** is one field of the `input:` map — `name`, `type`, a list of `rules`, a `sensitive` flag (keeps secrets out of logs and journals), and `of` for enum targets. `isRequired()` is true when `required` is among the rules; `isEnum()` is true for `type: enum` with an `of` class.
- **`OutputResponseSpec`** is one `output:` entry: an integer `status` and a `body` map of field name → type spec (e.g. `App\User\User` or `array<string, list<string>>`).
- **`DomainSpec`** is the service the Action delegates to — its `class` FQCN and the method to call (`invocation`, default `__invoke`).
- **`PersistenceSpec`** / **`QueueDispatchSpec`** are the optional blocks covered below.

`Spec::artifactName()` is the convention engine: it takes the domain class short-name (`App\User\CreateUser` → `CreateUser`) and every emitted artifact is named off it — `CreateUserAction`, `CreateUserInput`, `CreateUserResponder`, `CreateUserActionTest`. That is why the `domain.class` is required even for endpoints that barely have a domain layer: it is what names everything.

Loading goes through `SpecLoader`, which accepts either a single file or a directory:

```php
public function load(string $path, bool $validate = true): array   // list<Spec>
```

Point it at a directory and it walks recursively for `*.yaml` / `*.yml`, sorts the paths (so batch runs are deterministic), and returns one `Spec` per file. Validation is on by default; `Validator` collects every semantic error (unknown HTTP method, unknown validation rule, malformed FQCN, a persistence block with the wrong number of primary keys) and throws a single `SpecValidationException` carrying the full list — you fix all the errors at once rather than one round-trip per mistake.

### The emission plan and the emitters

`EmissionPlan::build()` is the pure heart of the package — it runs every emitter against a `Spec` and returns a `list<EmittedFile>` without touching disk:

```php
public function build(Spec $spec): array    // list<EmittedFile>
```

Each emitter returns an `EmittedFile` — a `relativePath`, the file `contents`, and an `EmittedFileKind` enum tag. The HTTP-side emitters always fire (`ActionEmitter`, `InputEmitter`, `ResponderEmitter`, `DomainStubEmitter`, `TestEmitter`, `OpenApiEmitter`, `RouteEmitter`). The persistence emitters (`EntityEmitter`, `RepositoryEmitter`, `MigrationEmitter`) fire only when the spec carries a `persistence:` block, and the repository emitter only when a repository FQCN is declared. The queue emitters (`MessageEmitter`, `HandlerEmitter`, `HandlerTestEmitter`) fire once per `queue:` entry. The full set of kinds:

```
Action  Input  Responder  DomainStub  Test  OpenApi  Route
Entity  Repository  Migration
Message  Handler  HandlerTest
```

Because `build()` is pure, the same `Spec` always yields byte-identical output — which is exactly what makes the snapshot tests, the drift linter, and the journal's replay-and-compare all possible.

### File writing and write statuses

`FileWriter` resolves each `EmittedFile`'s path against a project root and writes it, returning a `WriteOutcome` whose `WriteStatus` is one of three:

- **`Written`** — a new file landed on disk (or `--force` overwrote an existing one).
- **`Skipped`** — the file already existed and `--force` was not passed. This is the idempotent re-run case.
- **`Modified`** — reserved for the routes file. Route entries are never overwritten wholesale; instead the new entry is *appended* to the existing `config/routes.php` (a fresh one is created if absent), and the writer first checks whether the entry is already present so re-runs do not duplicate routes.

That three-way distinction is what lets `spec:scaffold` report "wrote N, skipped M" honestly, and what the journal records so a later `rewind` knows whether to delete a file (it was created) or restore its previous content (it was modified).

### The journal — rewind / replay safety net

The journal is what turns a one-shot generator into something you can run fearlessly. Every successful scaffold writes one `.altair/journal/<id>.json` entry per spec. The id is a sortable `<YYYYMMDDTHHMMSSZ>-<short-sha>` stem, and the entry is self-contained — it embeds the spec content (`spec.content_inline`), per-file SHAs, and the complete `content_before` for any file it modified — so it can be replayed or reversed even if the original spec file is later edited or deleted.

`Journal` is the read/write/query facade over `FilesystemStorage`:

```php
public function record(JournalEntry $entry): string;       // returns the written path
public function findById(string $idOrPrefix): JournalEntry; // full id or unambiguous prefix
public function tail(?int $limit = null): Generator;        // newest-first
public function history(): Generator;                       // oldest-first
public function rewind(JournalEntry $entry, bool $force = false): array;
public function replay(/* via ReplayCommand */);
```

`findById()` resolving a unique prefix matters for ergonomics — an agent (or you) can pass the first 8 characters of an id without juggling the full timestamp form. `rewind()` deletes created files (when their on-disk SHA still matches what was recorded) and restores modified files from `content_before`; the entry itself is not deleted, it gets a `reverted_at` stamp appended so the history stays auditable. If any file's SHA no longer matches — meaning you hand-edited it after scaffolding — `rewind()` throws `RewindRefusedException` listing the unsafe files unless `force: true` is given.

`SnapshotCollector` is the glue inside `ScaffoldCommand`: `captureBefore()` reads the existing content *before* the write, `record()` classifies each `WriteOutcome` into created / modified / skipped snapshots afterwards, and those snapshots become the `JournalEntry` via `JournalEntry::scaffold(...)`. Journaling is best-effort — a storage failure never fails the scaffold itself.

### Drift detection

`DriftDetector` re-parses the generated PHP and compares its AST back to the spec across four axes (see `DriftKind`):

- **`MissingInputField` / `UnknownInputField`** — the spec's input fields vs. the Input DTO's constructor params.
- **`MissingValidationRule`** — each spec rule vs. what the Input DTO's static `rules()` returns.
- **`ResponderMissingStatus`** — each spec response status vs. the Responder's static `statuses()`.
- **`UnregisteredRoute`** — whether the action FQCN appears in the routes file at all.

Each finding is a `DriftFinding` (`kind`, a human-readable `message`, a file `location`), collected into an immutable `DriftReport`. `DriftReport::hasDrift()` drives the `spec:lint` exit code. Drift is detected one direction at a time and reported precisely — the message tells you whether to fix the spec or fix the code.

### SDK emitters from the merged OpenAPI document

The SDK layer is independent of the spec AST — it works off the *merged OpenAPI 3.1 document*, so it can also compile a hand-authored OpenAPI file, not just framework-generated fragments. `OpenApiParser::parseYaml()` turns the document into a language-neutral model under `Altair\Scaffold\Sdk\Model` (operations, request/response schemas, `$ref` resolution, enum support, even synthesising an `operationId` when one is missing). Each emitter implements `EmitterInterface`:

```php
interface EmitterInterface
{
    public function language(): string;          // "typescript" | "python"
    public function defaultFileName(): string;   // "sdk.ts" | "client.py"
    public function emit(OpenApiDocument $document, bool $multiFile = false): EmittedSdk;
}
```

`EmitterRegistry::default()` ships both built-ins; `available()`, `has()`, and `get()` are the lookup surface. Emitters are *pure* — same document in, byte-identical `EmittedSdk` out — which is what makes the `--check` drift gate reliable. The TypeScript emitter produces a fetch-based, zero-runtime-dependency client with status-discriminated response unions; the Python emitter produces an `httpx` + `pydantic v2` client with both sync and async classes, targeting `mypy --strict`.

### Optional `persistence:` and `queue:` spec blocks

The same spec file can carry two optional blocks that extend the emission plan into adjacent packages:

- A **`persistence:`** block (a `PersistenceSpec`) emits a Cycle-annotated entity, a typed repository (when a repository FQCN is given), and a Cycle migration — kept in lockstep with the HTTP artifacts so the wire format and the storage shape never diverge. See [persistence.md](./persistence.md).
- A **`queue:`** block (one or more `QueueDispatchSpec`) emits, per entry, a readonly message DTO, an `#[AsHandler]`-decorated handler stub, and a PHPUnit test. See [messaging.md](./messaging.md).

Both are validated alongside the rest of the spec: persistence fields must use a known column type and declare exactly one primary key; queue field types must be a scalar or an FQCN.

## Usage

### Writing a spec

Write the YAML first — the scaffolder is the only thing that should produce the Action/Input/Responder triple. A fuller spec:

```yaml
# api/users/create.yaml
endpoint:
  method: POST
  path: /users
  summary: Create a user
  tags: [users]

input:
  email:    { type: string, rules: [email, required] }
  password: { type: string, rules: [min:8, required], sensitive: true }

output:
  201: { body: { user: App\User\User } }
  422: { body: { errors: 'array<string, list<string>>' } }
  409: { body: { message: string } }

domain:
  class: App\User\CreateUser
```

The validator will reject the spec if `method` is not a real HTTP verb, if `path` does not start with `/`, if any rule is not one of the known `Altair\Validation\Rule\*` rules (`required alpha alphanum between boolean callback creditcard datetime email iban in integer ip isbn max min regex swiftbic url zipcode`), if a response status is outside `100..599`, or if `domain.class` is not a well-formed FQCN. Mark any secret-bearing field `sensitive: true` so it is kept out of generated logging and journal snapshots.

### Scaffolding

```bash
bin/altair spec:scaffold api/users/create.yaml          # one spec
bin/altair spec:scaffold api/                           # every spec under a directory
bin/altair spec:scaffold api/users/create.yaml --dry-run
bin/altair spec:scaffold api/ --force                   # regenerate, overwriting
bin/altair spec:scaffold api/users/create.yaml --root=/abs/path/to/project
```

`--root` overrides the project root used as the base for emitted paths; without it, the command walks up from the current working directory to the nearest `composer.json`. The default behaviour skips files that already exist, so the safe loop is: edit the spec, `--dry-run` to preview, then `--force` to regenerate.

### Regenerate vs. hand-edit, and the `spec:lint` drift gate

Generated files are real files in your repo — you *can* hand-edit them, and sometimes you should (a bespoke query in a repository, a non-trivial responder). The contract is: when you change the *spec*, regenerate; when you hand-edit *generated code*, run the linter so the divergence is visible.

```bash
bin/altair spec:lint                 # defaults to scanning api/
bin/altair spec:lint api/users/create.yaml
bin/altair spec:lint --root=/abs/path/to/project
```

`spec:lint` exits `1` when it finds any drift, printing each finding as `[<kind>] <message>`, and `0` when clean. Wire it into CI next to `composer cs`, `composer stan`, and `composer test`:

```yaml
# .github/workflows/ci.yml
- name: Ensure scaffolded code matches its specs
  run: bin/altair spec:lint
```

### Undoing and re-applying with the journal

> **Journal commands require host wiring.** The `journal:*` commands take a non-nullable `Journal` constructor dependency, and they live in `src/Altair/Scaffold/Journal/Cli` — a *sibling* of `src/Altair/Scaffold/Cli`, which is the only scaffold directory the framework's `bin/altair` adds to its command path. So out of the box the framework binary exposes `spec:*` but not `journal:*`. A host application enables the journal by (1) applying `ScaffoldJournalConfiguration` to its container, and (2) adding `…/src/Altair/Scaffold/Journal/Cli` to `ALTAIR_CLI_PATHS` (see [cli.md](./cli.md)). With both in place, `spec:scaffold` starts writing journal entries and the commands below light up.

```bash
bin/altair journal:list -n 50                  # newest 50 entries (or --format=json)
bin/altair journal:show <id>                   # full detail (resolves unambiguous prefixes)
bin/altair journal:diff <id>                   # per-file diffs embedded in the entry
bin/altair journal:rewind                      # undo the most recent scaffold
bin/altair journal:rewind --to=<id>            # undo back to (and including) an entry
bin/altair journal:rewind --dry-run            # preview what would be undone
bin/altair journal:rewind --force              # override the hand-edit safety check
bin/altair journal:replay --id=<id>            # re-apply one entry from its embedded spec
bin/altair journal:replay --from=<id>          # re-apply forward from a point
bin/altair journal:replay --all                # replay the whole journal (confirms first)
bin/altair journal:replay --force              # overwrite existing files while replaying
```

`journal:rewind` works newest-first and refuses to clobber files you have edited since the original scaffold (SHA mismatch) — re-run with `--force` to override, and it will tell you exactly which files were unsafe. `journal:replay` reads the spec out of the entry's embedded `content_inline`, never the original file, and reports drift (a `1` exit) when the regenerated content no longer matches what the journal recorded — which usually means the scaffolder itself changed between the original run and the replay.

The `journal:*` commands are named to avoid colliding with the introspection sub-package's `spec:list` / `spec:show`, which view raw YAML specs rather than scaffold-time history.

### Emitting OpenAPI and SDKs, with the `--check` CI drift gate

`spec:scaffold` already drops a per-endpoint OpenAPI fragment under `docs/openapi/`. Merge them into one document:

```bash
bin/altair spec:emit-openapi                       # merged 3.1 doc → stdout
bin/altair spec:emit-openapi --out=docs/openapi.yaml
bin/altair spec:emit-openapi --pretty              # 4-space indent
bin/altair spec:emit-openapi --fragments=docs/openapi --root=/abs/path
```

Then compile typed clients from that merged document:

```bash
bin/altair spec:emit-sdk --list                          # list available languages
bin/altair spec:emit-sdk typescript > sdk.ts             # single-file, to stdout
bin/altair spec:emit-sdk typescript --out=sdk.ts
bin/altair spec:emit-sdk typescript --out=clients/ts --multi-file   # types.ts + client.ts
bin/altair spec:emit-sdk python --out=clients/python
bin/altair spec:emit-sdk typescript --openapi=docs/openapi.yaml     # compile a given doc
bin/altair spec:emit-sdk typescript --out=sdk.ts --check            # exit 1 on drift
```

When `--openapi` is omitted the command merges `docs/openapi/*.yaml` on the fly, so the SDK always reflects the current specs. Because emission is deterministic, `--check` is a true CI gate — it regenerates in memory and diffs against the files on disk, exiting `1` (and listing the drifted files) when the committed SDK has fallen behind the spec. Do not hand-edit emitted SDKs; regenerate them.

```yaml
# .github/workflows/ci.yml
- name: SDKs are current
  run: |
    bin/altair spec:emit-sdk typescript --out=clients/ts/sdk.ts --check
    bin/altair spec:emit-sdk python --out=clients/python --multi-file --check
```

## Configuration

There is nothing to configure for the core `spec:*` commands — they are plain `readonly` invokables with new-on-default dependencies, auto-discovered by `bin/altair` because it adds `src/Altair/Scaffold/Cli` to the command path at startup.

The journal is the one part that needs wiring, and it is opt-in. `ScaffoldJournalConfiguration` binds `Journal`, `FilesystemStorage`, and `FileDiffer` into `Altair\Container`, reading three env vars:

| Variable | Default | Purpose |
|---|---|---|
| `ALTAIR_JOURNAL_ENABLED` | `true` | Set `false` to skip binding the journal. |
| `ALTAIR_JOURNAL_DIR` | `.altair` | Base directory, relative to the project root. |
| `ALTAIR_JOURNAL_SUBDIR` | `journal` | Journal subdirectory under the base. |

```php
use Altair\Configuration\Support\Env;
use Altair\Container\Container;
use Altair\Scaffold\Journal\Configuration\ScaffoldJournalConfiguration;

$container = new Container();
$container->share(new Env());

(new ScaffoldJournalConfiguration(projectRoot: __DIR__))->apply($container);
```

`ScaffoldCommand` resolves `Journal` (and `RecorderInterface` from `univeros/events`) as *optional* constructor dependencies — both nullable — so a minimal host can scaffold without applying this configuration at all. Bind the journal and `spec:scaffold` starts recording; additionally bind the events recorder (via `EventsConfiguration`) and each scaffold also appends a `scaffold` mutation event to `.altair/events.jsonl` for cross-session agent memory. See [events.md](./events.md).

Keep `.altair/` in your application's `.gitignore` — journal entries are local to a checkout, not shared history.

## Testing

The published tests under `tests/Scaffold/` are the most honest description of how each component behaves:

- `tests/Scaffold/Spec/ParserTest.php`, `ValidatorTest.php`, `PersistenceParserTest.php`, `PersistenceValidatorTest.php` — YAML → AST parsing and the full semantic validation matrix.
- `tests/Scaffold/Emitter/*EmitterTest.php` — one per emitter, each asserting the generated output against a golden snapshot under `tests/Scaffold/Snapshots/` (`CreateUserAction.php.txt`, `CreateUserInput.php.txt`, `User.php.txt`, `create-user.openapi.yaml`, and so on).
- `tests/Scaffold/Cli/ScaffoldCommandIntegrationTest.php` — end-to-end: a spec run through the command into a temp project tree, asserting written / skipped / modified outcomes.
- `tests/Scaffold/Journal/*` — entry serialisation (`JournalEntryTest`), the SHA-guarded rewind logic (`JournalRewindTest`), atomic storage (`Storage/FilesystemStorageTest`), the unified differ, and the `journal:*` commands.
- `tests/Scaffold/Linter/DriftDetectorTest.php` — each `DriftKind` exercised against a deliberately-divergent fixture.
- `tests/Scaffold/Sdk/*` — the OpenAPI parser, both emitters, the registry, the `spec:emit-sdk` command, and a compile-integration test driven by `tests/Scaffold/Sdk/Fixtures/users-api.yaml`.

The pattern to mirror when you extend an emitter: a `SpecFixture` builder under `tests/Scaffold/Support/`, a golden snapshot under `tests/Scaffold/Snapshots/`, and a test that diffs the emitter output against the snapshot. Determinism is the whole value proposition — these snapshot tests are what defend it. After an intentional change to an emitter, regenerate and diff the snapshot before committing.

## Extending

### Add an SDK language

The natural extension point is a new SDK target. Implement `EmitterInterface` — `language()` returns the CLI identifier, `defaultFileName()` the single-file output name, and `emit()` walks the `OpenApiDocument` model to produce an `EmittedSdk` (a map of relative path → contents):

```php
use Altair\Scaffold\Sdk\Contracts\EmitterInterface;
use Altair\Scaffold\Sdk\EmittedSdk;
use Altair\Scaffold\Sdk\Model\OpenApiDocument;

final readonly class GoEmitter implements EmitterInterface
{
    public function language(): string { return 'go'; }
    public function defaultFileName(): string { return 'client.go'; }

    public function emit(OpenApiDocument $document, bool $multiFile = false): EmittedSdk
    {
        // walk $document->operations / $document->namedSchemas …
        return new EmittedSdk(['client.go' => $source]);
    }
}
```

Then register it by constructing `EmitterRegistry` with your emitter alongside the defaults — do **not** fork the TypeScript or Python emitters. Keep your `emit()` deterministic (no `microtime()`, no unordered iteration); the contract requires byte-identical output for identical input so the `--check` gate stays meaningful.

### Add a new generated artifact

To emit an extra file from a spec, write an emitter that returns an `EmittedFile` with a new `EmittedFileKind`, then add it to `EmissionPlan::build()` (guarding it behind a spec block if it should only fire conditionally, the way the persistence and queue emitters are). Add a snapshot test, and — if the artifact should participate in drift detection — extend `DriftDetector` with a new `DriftKind`.

### Add a new spec scaffolder downstream

When you build a new generator in a downstream package, do **not** bypass `ScaffoldCommand`. Write a YAML spec and call `spec:scaffold`. Routing through the command is what gets you the journal entry and the event-log dual-write for free, and what keeps `journal:rewind` and `journal:replay` working over your new artifacts.

## Related packages

- [persistence.md](./persistence.md) — the Cycle ORM bridge. A `persistence:` block makes the scaffolder emit an entity, a repository, and a migration.
- [messaging.md](./messaging.md) — the Symfony Messenger bridge. A `queue:` block makes the scaffolder emit a message DTO, an `#[AsHandler]` handler stub, and a test.
- [events.md](./events.md) — the append-only mutation log. When bound, each scaffold (and journal rewind/replay) records a `scaffold` / `rewind` / `replay` event to `.altair/events.jsonl`.
- [cli.md](./cli.md) — the attribute-driven CLI runtime that hosts every `spec:*` and `journal:*` command, plus the `ALTAIR_CLI_PATHS` mechanism the journal commands rely on.
- [mcp.md](./mcp.md) — the MCP server. Its `framework__scaffold` / `framework__emit_*` tools wrap these commands so an agent can scaffold, lint, and emit SDKs over the wire.
- [http.md](./http.md) — the runtime the generated Action / Input / Responder triple plugs into. The scaffolder generates against its conventions; the HTTP package actually serves the request.

## Limitations

- **Conventions are fixed in `Naming`.** Output paths and namespaces (`app/Http/Actions`, the `App\` namespace, `config/routes.php`, `database/migrations`) are baked into the `Naming` helper. Different layouts require constructing `Naming` with overrides and threading it through the emitters — there is no per-spec or config-file override for the paths yet.
- **Validation rules are an allow-list.** Only the rules that ship with `Altair\Validation\Rule\*` are recognised; a custom rule will be flagged as unknown by the validator. Add it to the validator's known-rule set or it will not pass `spec:scaffold`.
- **Drift detection is structural, not behavioural.** The linter checks input fields, validation rules, response statuses, and route registration. It does *not* verify that a hand-edited Action still does the right thing — only that the spec and the generated surface agree on shape.
- **The journal is per-checkout, not shared.** Entries live under `.altair/` and are gitignored. `journal:rewind` / `replay` operate on your local history; they do not coordinate across machines or branches.
- **The SDK emitters cover the subset the framework produces.** The OpenAPI parser understands operations, JSON request/response bodies, `$ref`, enums, and OpenAPI 3.1's `type: [t, "null"]` nullable form — enough for framework-generated documents plus common hand-authored ones. Exotic OpenAPI features (callbacks, `oneOf`/`allOf` composition, security schemes) are not modelled and are silently ignored.
- **`journal:*` commands need explicit host wiring.** They are not exposed by the framework's `bin/altair` by default — the host must apply `ScaffoldJournalConfiguration` and add the journal CLI directory to `ALTAIR_CLI_PATHS` (see the Usage note above).
