# Introspection

> A read-only X-ray of a booted application: container bindings, routes, listeners, middleware, specs, and config — surfaced for humans and agents alike, and never triggering a single dispatch, `make()`, or migration.

**Composer:** `univeros/introspection`
**Namespace:** `Altair\Introspection`

## Introduction

When you (or an agent acting for you) sit down in front of an unfamiliar Altair application, the first question is always the same: *what is actually wired into this thing right now?* Which services did the container register? Which routes will dispatch? In what order does middleware run? Which event listeners fire, and at what priority? Are the YAML specs on disk the ones the scaffolder will read? Did somebody hand-edit a `.agent/` manifest out of sync with the source?

This package answers those questions without you having to read a single line of bootstrap code. You boot the application as usual, then point an inspector at the live container and collections. Every inspector is read-only by construction — it walks the data structures the framework already built and reports their shape and state. It never calls `make()`, never dispatches an event, never resolves the middleware Relay, never runs a migration. That property is the whole point: you can run `container:inspect` against a project whose database is down, whose `prepare` hooks have side effects, or whose worker process you do not want to perturb, and nothing happens except a table comes back.

The output is uniform. Every inspector returns the same value object — an `InspectionTable` — and that single shape feeds two renderers: a fixed-width human table for your terminal and pretty-printed JSON for machine consumption. The JSON output is deterministic, so an AI agent can parse it, branch on it, and diff it against a previous run. The same `framework__*_inspect` MCP tools in [mcp.md](./mcp.md) wrap these very inspectors, which is why an agent gets the same answer through the MCP bridge that you get from the CLI.

What this package deliberately does **not** do: it does not modify anything (no inspector has a write path), it does not describe *behaviour* (it tells you a listener is registered, not what it will do when it fires), and it does not boot the application for you — you bring a container that has already had its configurations applied, and the inspectors read what is there.

## Installation

Standalone:

```bash
composer require univeros/introspection
```

This pulls in `symfony/yaml` (for the spec inspector) plus the four framework packages the inspectors read from: `univeros/cli` (the attribute-driven command runtime that hosts the `bin/altair` commands), `univeros/container`, `univeros/happen`, and `univeros/http`. No PHP extensions beyond core 8.3 are required.

If you are installing the full framework, `composer require univeros/framework` already bundles this package.

## Quick start

The fastest way to see what introspection gives you is the `bin/altair` commands. Every command takes `--format=human` (the default, a terminal table) or `--format=json` (deterministic, agent-readable). Start with the container — it shows every binding the application registered:

```bash
bin/altair container:inspect
bin/altair container:inspect --shared            # only singletons
bin/altair container:inspect --filter=Repository # case-insensitive substring on the id
bin/altair container:inspect --realized          # only services actually instantiated so far
bin/altair container:inspect "App\\User\\UserRepository" --format=json   # drill into one binding
```

Routes, listeners, and middleware follow the same shape — list everything, or zoom into one entry:

```bash
bin/altair routes:list --format=json
bin/altair routes:show /users
bin/altair listeners:list
bin/altair listeners:show user.created --format=json
bin/altair middleware:list
```

Specs and config round it out. `config:dump` masks secret-looking keys by default — pass `--no-secrets=false` only inside a trusted shell to see raw values:

```bash
bin/altair spec:list
bin/altair spec:show users/create.yaml --format=json
bin/altair config:dump                  # secrets masked
bin/altair config:dump --no-secrets=false
bin/altair manifest:diff --format=json  # exits non-zero on drift
```

All of these auto-load: the `bin/altair` entry point discovers the commands through the `univeros/cli` attribute scanner (each command class carries a `#[Command]` attribute), so installing the package is enough — no opt-in registration.

## Concepts

There are three moving parts, and they compose cleanly.

**Inspectors are read-only wrappers over already-shared collections.** Each inspector takes, in its constructor, the live collection it reads — `ContainerInspector` takes the `Container`, `RouteInspector` takes the `Http\Collection\RouteCollection`, `PipelineInspector` takes the `Http\Collection\MiddlewareCollection`, `ListenerInspector` takes the concrete `Happen\EventDispatcher`. None of them instantiate anything. `ContainerInspector`, for example, walks the container's six binding collections (aliases, shares, delegates, class definitions, parameters, prepares) via reflection only; its `inspectRealized()` view reads the *already-constructed* instances sitting in the shares collection and calls `::class` on them, but it never serialises (which would fire `__sleep`) and never `make()`s.

**`InspectionTable` is the uniform result.** Every inspector method returns:

```php
final readonly class InspectionTable
{
    public function __construct(
        public string $title,
        public array $columns,   // list<string> — authoritative column order
        public array $rows,      // list<array<string, mixed>> — keyed by column
        public array $extras = [],   // sidecar data shown only in JSON (totals, paths)
    ) {}

    public function isEmpty(): bool;

    // array{ title, columns, rows, extras? }
    public function toArray(): array;
}
```

The `columns` list is authoritative: the renderer iterates it to project each row, so a missing key just becomes an empty cell. `extras` carries metadata — totals, source paths, an `in_sync` flag — that the JSON renderer emits but the human table omits.

**One table feeds two renderers.** `InspectionTable → RendererInterface → human|json`. `RendererRegistry::default()` ships `human` (a `TableRenderer` that sizes each column to its widest value) and `json` (a `JsonRenderer` that emits pretty-printed, deterministic, byte-stable JSON). Hosts can pre-bind their own renderer (HTML, CSV) into the registry before bootstrapping the CLI; the CLI commands resolve the right one from the `--format` flag.

One renderer detail worth knowing: `config:dump` masks secrets **by key-name pattern, not by value**. `ConfigInspector` flags any key whose name contains one of `PASSWORD`, `SECRET`, `TOKEN`, `KEY`, `CREDENTIAL`, `PRIVATE`, `AUTH`, `BEARER`, `API_KEY`, or `ACCESS_KEY` (case-insensitive substring) and replaces the value with `***`. This is a heuristic — a key named `MONKEY_COUNT` will be masked because it contains `KEY` — but it fails *safe*: it would rather over-redact than leak.

## Usage

### Calling an inspector programmatically

You do not have to go through the CLI. Resolve an inspector from the container and read its `InspectionTable` directly — `toArray()` gives you a structure you can assert against in a test, ship to a dashboard, or hand to an agent. Here is a real end-to-end example against the container inspector:

```php
use Altair\Container\Container;
use Altair\Introspection\Configuration\IntrospectionConfiguration;
use Altair\Introspection\Inspector\ContainerInspector;

$container = new Container();

// ... your application's Configurations have already run, registering bindings ...

(new IntrospectionConfiguration())->apply($container);

/** @var ContainerInspector $inspector */
$inspector = $container->make(ContainerInspector::class);

// Full inventory, singletons only, names containing "repository".
$table = $inspector->inspectAll(sharedOnly: true, filter: 'repository');

foreach ($table->rows as $row) {
    printf("%-50s %-10s shared=%s\n", $row['id'], $row['kind'], $row['shared'] ? 'yes' : 'no');
}

echo "total: {$table->extras['total']}\n";

// Drill into one binding — includes its constructor dependencies via reflection.
$detail = $inspector->inspectOne(App\User\UserRepository::class);
var_export($detail->toArray());
```

Because `toArray()` is the same shape the JSON renderer emits, you can also feed it straight through a renderer when you want a string instead of an array:

```php
use Altair\Introspection\Renderer\RendererRegistry;

$registry = RendererRegistry::default();
echo $registry->get('json')->render($table);   // deterministic JSON
echo $registry->get('human')->render($table);  // fixed-width table
```

### The inspectors, and when to reach for each

Each inspector solves one question. The relevant signatures are quoted so you know exactly what you can call.

**`ContainerInspector`** — *"what did the application register, and what has it built?"*

```php
public function inspectAll(bool $sharedOnly = false, ?string $filter = null): InspectionTable
public function inspectRealized(?string $filter = null): InspectionTable
public function inspectOne(string $id): InspectionTable
public function collectBindings(): iterable   // raw row stream, no filters
```

`inspectAll()` reports definitions — what *would* be built. `inspectRealized()` reports the complementary view — singletons the container has *actually* constructed so far, which is the tool you want when debugging worker memory growth, a surprised-by-singleton, or `prepare`-hook ordering in a long-running process. `inspectOne()` adds constructor dependencies (via reflection) and throws `NotFoundException` when no binding matches. `collectBindings()` is the unfiltered generator behind `inspectAll()` — reach for it when you want to walk every row yourself.

**`RouteInspector`** — *"which routes will dispatch, to which actions?"* Constructed with a `RouteCollection`.

```php
public function inspectAll(): InspectionTable
public function inspectOne(string $path): InspectionTable   // throws NotFoundException
```

Walking the collection never triggers dispatch or middleware resolution. A path can appear under several HTTP methods, so `inspectOne()` returns every registration for that path.

**`ListenerInspector`** — *"what fires on this event, and in what order?"* Constructed with the **concrete** `Altair\Happen\EventDispatcher` (not just the PSR-14 interface — the priority-sorted listener map lives on the concrete class; pass anything else and the constructor throws `IntrospectionException`).

```php
public function inspectAll(): InspectionTable
public function inspectOne(string $event): InspectionTable   // listeners in priority order
```

**`PipelineInspector`** — *"in what order does middleware run?"* Constructed with a `MiddlewareCollection` (and an optional pipeline name).

```php
public function inspectAll(): InspectionTable   // in dispatch order
```

It walks the collection's items directly rather than instantiating the Relay, so it stays lazy-binding safe. Hosts with multiple named pipelines register one inspector per pipeline.

**`SpecInspector`** — *"what scaffolder specs are on disk, and is this one well-formed?"* Constructed with the spec root.

```php
public function inspectAll(): InspectionTable
public function inspectOne(string $path): InspectionTable   // throws NotFoundException
```

It parses YAML directly with `symfony/yaml` rather than going through the scaffolder's `SpecLoader`, so it will list and flatten specs that do *not yet* pass scaffolder validation — exactly what you want when debugging "why won't this spec scaffold?"

**`ConfigInspector`** — *"what configuration is this process actually seeing?"* Constructed with the `Container` plus optional extra secret patterns.

```php
public function dump(bool $maskSecrets = true): InspectionTable
public const array DEFAULT_SECRET_PATTERNS = ['PASSWORD', 'SECRET', 'TOKEN', 'KEY', /* ... */];
public const string REDACTED = '***';
```

It merges `getenv()`, `$_SERVER`, and `$_ENV` (in that precedence order, `$_ENV` winning) plus the container's parameter definitions, so the output matches what `Altair\Configuration\Support\Env` would see at runtime. Secret masking is on by default and described in [Concepts](#concepts) above.

**`ManifestDiffInspector`** — *"have the `.agent/` manifests drifted from the source?"* Constructed with the manifest root.

```php
public function diff(array $regenerated): InspectionTable   // path => expected content
```

Pure by design: it does no manifest regeneration of its own. You pass in a freshly-regenerated `path => content` map (produced by [agent-spec.md](./agent-spec.md)); it SHA-256s both sides and buckets the result into `stale`, `missing`, and `extra`. The `extras['in_sync']` flag drives the CLI exit code.

### A note on secret masking

Because `ConfigInspector` masks by key *name*, you control the policy two ways: extend the pattern list at the container level (see [Configuration](#configuration)), or pass `maskSecrets: false` to `dump()` when you genuinely need raw values inside a trusted, non-logged shell. The default — masking on — is the right choice for anything an agent or CI job will read.

## Configuration

`IntrospectionConfiguration` binds every inspector as a **shared** service. The inspectors are stateless wrappers over already-shared collections, so sharing them costs nothing:

```php
use Altair\Container\Container;
use Altair\Introspection\Configuration\IntrospectionConfiguration;

$container = new Container();

(new IntrospectionConfiguration(
    projectRoot: '/path/to/app',          // defaults to getcwd()
    specRoot: '/path/to/app/api',         // defaults to <project>/api
    manifestRoot: '/path/to/app/.agent',  // defaults to <project>/.agent
    extraSecretPatterns: ['PASSPHRASE'],  // appended to ConfigInspector's defaults
))->apply($container);
```

The important design property is **graceful degradation by optional dependency**. The route, listener, and middleware inspectors need collections that a minimal host might not bind — `RouteCollection`, `EventDispatcher`, `MiddlewareCollection`. Because each inspector is bound through an independent `delegate`, applying this configuration against such a host is still safe: nothing resolves until you actually ask for an inspector. The CLI commands type-hint individual inspectors, so a missing dependency fails *only the command that needs it* — `config:dump` and `container:inspect` keep working on a host that never wired FastRoute, Happen, or Relay. You apply the configuration once and get whatever subset of inspectors your application supports.

See [container.md](./container.md) for the `delegate` / `share` binding API the configuration uses.

## Testing

The test suite under `tests/Introspection/` is the most honest description of how each piece behaves:

- `tests/Introspection/Inspector/ContainerInspectorTest.php` — binding inventory, filters, the realised view, and `inspectOne()` detail.
- `tests/Introspection/Inspector/LazyBindingSafetyTest.php` — the load-bearing guarantee: inspecting never triggers instantiation. Read this one to understand why the package is safe against a project with side-effecting `prepare` hooks.
- `tests/Introspection/Inspector/RouteInspectorTest.php`, `ListenerInspectorTest.php`, `PipelineInspectorTest.php` — one per collection-backed inspector.
- `tests/Introspection/Inspector/ConfigInspectorTest.php` — secret masking, env-source precedence.
- `tests/Introspection/Inspector/SpecInspectorTest.php`, `ManifestDiffInspectorTest.php` — filesystem-backed inspectors against fixture trees.
- `tests/Introspection/Renderer/RenderersTest.php` — the human and JSON renderers, including JSON determinism.
- `tests/Introspection/Configuration/IntrospectionConfigurationTest.php` — that every inspector binds as a shared service and degrades gracefully.
- `tests/Introspection/Cli/CommandsSmokeTest.php` — each `bin/altair` command boots and returns the right exit code.

When you add a new inspector, mirror the pattern: a focused inspector test asserting `toArray()`, a lazy-binding-safety assertion if it touches the container, and a smoke test for its CLI command.

## Related packages

- [container.md](./container.md) — the binding collections `ContainerInspector` and `ConfigInspector` read from, and the `delegate`/`share` API `IntrospectionConfiguration` uses.
- [http.md](./http.md) — the `RouteCollection` and `MiddlewareCollection` behind `routes:*` and `middleware:list`.
- [happen.md](./happen.md) — the `EventDispatcher` whose priority-sorted listener map drives `listeners:*`.
- [scaffold.md](./scaffold.md) — the YAML specs that `spec:list` / `spec:show` surface (parsed independently of the scaffolder so malformed specs still list).
- [agent-spec.md](./agent-spec.md) — the manifest generator whose `.agent/` output `manifest:diff` compares against.
- [mcp.md](./mcp.md) — the `framework__*_inspect` MCP tools wrap these inspectors so an agent gets identical answers through the MCP bridge.
- [cli.md](./cli.md) — the attribute-driven CLI runtime that hosts every `bin/altair` introspection command.

## Limitations

- **Collection inspectors need the host to bind their collections.** `routes:*`, `listeners:*`, and `middleware:list` require the host to have wired `RouteCollection`, the concrete `EventDispatcher`, and `MiddlewareCollection` respectively. On a host that does not, those specific commands fail (with a clear message) while the rest keep working — see [Configuration](#configuration).
- **Shape and state, not behaviour.** An inspector tells you a listener is registered for `user.created` at a given priority; it cannot tell you what that listener *does* when it fires. For behaviour, read the source or the per-package guides under `docs/packages/`.
- **`ListenerInspector` requires the concrete dispatcher.** Hosts running a custom PSR-14 dispatcher (not `Altair\Happen\EventDispatcher`) need their own inspector — the priority-sorted listener map this one reads lives on the concrete class.
- **`config:dump` masks by key name, not value.** The substring heuristic over-redacts before it under-redacts. A non-secret key that happens to contain `KEY`, `AUTH`, etc. will be masked; pass `--no-secrets=false` in a trusted shell when that gets in your way.
- **`manifest:diff` needs a regenerator to find drift.** With no `path => content` map supplied, it treats the on-disk `.agent/` tree as canonical and reports in-sync. Real drift detection requires the host to feed it freshly-regenerated manifests from [agent-spec.md](./agent-spec.md).
