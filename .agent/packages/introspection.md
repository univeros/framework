# univeros/introspection  ·  Altair\Introspection

**Purpose:** What's wired into this project *right now*? Family of CLI commands + inspectors for the Container, routes, listeners, middleware, manifests, specs, and configuration. Read-only — never triggers `make()` or `prepare` hooks, so it's safe against a project whose database is down or whose Configurations have side effects.

## CLI commands

| Command | Purpose |
|---|---|
| `container:inspect [id]` | Full binding inventory, or targeted detail. Filter via `--shared`, `--filter=<substr>`. |
| `routes:list` | Every registered route, alpha-sorted by path. |
| `routes:show <path>` | All registrations for one path (across HTTP methods). |
| `listeners:list` | Event names with at least one listener, plus listener counts. |
| `listeners:show <event>` | Listeners for one event in priority order. |
| `middleware:list` | PSR-15 middleware pipeline in dispatch order. |
| `manifest:diff` | Drift report between on-disk `.agent/` and a fresh regeneration (non-zero exit on drift). |
| `spec:list` | Every YAML spec under the configured root, with method + path. |
| `spec:show <path>` | Parsed + flattened view of one spec file. |
| `config:dump` | Merged env + Container parameters; `--no-secrets` masking on by default. |

All accept `--format=human` (default) or `--format=json` for agent / MCP consumption.

> **Naming:** uses `listeners:*` rather than `events:*` to avoid collision with the mutation event log (`univeros/events` — issue #77).

## Inspectors

| Inspector | Reads | Notes |
|---|---|---|
| `ContainerInspector` | `Altair\Container\Container` — aliases, shares, delegates, class & parameter definitions, prepare hooks | Walks the 6 binding collections directly — never calls `make()`. |
| `RouteInspector` | `Altair\Http\Collection\RouteCollection` | |
| `ListenerInspector` | `Altair\Happen\EventDispatcher` | Requires the concrete dispatcher (for the priority-sorted listener map). |
| `PipelineInspector` | `Altair\Http\Collection\MiddlewareCollection` | Walks the Queue directly; doesn't instantiate Relay. |
| `ConfigInspector` | env + `Container::getParameterDefinitions()` | Masks `PASSWORD`/`SECRET`/`TOKEN`/`KEY`/`AUTH`/`BEARER`/`API_KEY`/... by default. |
| `SpecInspector` | YAML files under `./api/` (default) | Tolerant of files that fail scaffolder validation. |
| `ManifestDiffInspector` | `.agent/` tree vs. caller-supplied regenerated content | Reports `stale`, `missing`, `extra` buckets. |

## Renderers

`TableRenderer` (human) and `JsonRenderer` (machine), both deterministic. Pluggable via `RendererRegistry`.

## Configuration

```php
use Altair\Introspection\Configuration\IntrospectionConfiguration;

(new IntrospectionConfiguration(
    projectRoot: __DIR__,
    specRoot: __DIR__ . '/api',         // optional, defaults to <project>/api
    manifestRoot: __DIR__ . '/.agent',  // optional, defaults to <project>/.agent
    extraSecretPatterns: ['INTERNAL'],  // optional
))->apply($container);
```

## Wiring example

```php
use Altair\Introspection\Inspector\ContainerInspector;

$inspector = $container->make(ContainerInspector::class);
$inventory = $inspector->inspectAll(sharedOnly: true, filter: 'Cache');
foreach ($inventory->rows as $row) {
    echo "{$row['id']}  →  {$row['target']}\n";
}
```

## Cross-package additive changes (shipped in this PR)

- `Altair\Container\Container::getPrepares(): PreparesCollection` — read-only accessor for prepare hooks. Sibling of the existing `getAliases()`, `getShares()`, `getDelegates()`, `getClassDefinitions()`, `getParameterDefinitions()`.
- `Altair\Happen\EventDispatcher::getEventNames(): list<string>` — enumerate registered event names without dispatching.
- `Altair\Happen\EventDispatcher::listenerCount(string): int` — count listeners for one event without invoking them.

All three are additive; existing call sites are unaffected.

## Tests as documentation

- `tests/Introspection/Inspector/ContainerInspectorTest.php`
- `tests/Introspection/Inspector/RouteInspectorTest.php`
- `tests/Introspection/Inspector/ListenerInspectorTest.php`
- `tests/Introspection/Inspector/PipelineInspectorTest.php`
- `tests/Introspection/Inspector/ConfigInspectorTest.php`
- `tests/Introspection/Inspector/SpecInspectorTest.php`
- `tests/Introspection/Inspector/ManifestDiffInspectorTest.php`
- `tests/Introspection/Inspector/LazyBindingSafetyTest.php` ← proves the no-instantiation contract
- `tests/Introspection/Renderer/RenderersTest.php`
- `tests/Introspection/Configuration/IntrospectionConfigurationTest.php`
- `tests/Introspection/Cli/CommandsSmokeTest.php`

## Related packages

- `symfony/yaml`
- `univeros/cli`
- `univeros/configuration`
- `univeros/container`
- `univeros/happen`
- `univeros/http`

## Issue

[#71 Introspection commands — container, routes, events, middleware, manifests, config](https://github.com/univeros/framework/issues/71)

## Follow-ups (tracked separately)

- #83 — `container:inspect --realized` flag (long-running-process introspection)
- #84 — MCP tool wrappers for every introspection command
- #85 — `univeros/tinker` interactive REPL
- #86 — `univeros/observability` runtime profiling
- #87 — Auto-suggest refactors from introspection output
