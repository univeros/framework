# Suggest

> A refactor adviser that walks the introspection surface — container bindings, routes, listeners, middleware, specs — and answers "what *should* change?": dead bindings and events, fat constructors, routes with no spec, orphan middleware. Scannable text for you, deterministic JSON an agent or CI step can act on.

**Composer:** `univeros/suggest`
**Namespace:** `Altair\Suggest`

## Introduction

[`univeros/introspection`](./introspection.md) answers *what is wired into this project right now?* — it is a stable, deterministic primitive. Suggest is the layer on top that answers the harder question: *what looks wrong, and what should I do about it?* It takes the same structural facts the inspectors expose and runs a set of heuristics over them, emitting actionable suggestions instead of raw tables.

Each heuristic is one small, swappable `SuggestionRule`. A rule is a pure function of a `Snapshot` — the immutable, structural projection of the project — so it does no I/O, no reflection, and no instantiation of its own. All of that happens once, up front, in the `SnapshotFactory`. That separation is the whole design: the impure gathering is isolated and tested once; the rules are trivial to write, trivial to test (hand-build a `Snapshot`, assert on the suggestions), and trivial to add or remove.

The findings are graded. `warning` is reserved for high-confidence "this does nothing" results — an event registered with zero listeners. `info` is advisory — "this constructor has eight collaborators, consider splitting it", "this binding looks unreferenced, but verify it is not an entry point first". The grading drives both the `--severity` floor filter and the process exit code, so `bin/altair suggest --severity=warning` is a CI gate that fails only on the things the analyser is sure about.

What Suggest deliberately does *not* do: it does not read your source code. Every signal comes from the runtime introspection surface, which is why the rules are honest about their false-positive surface (a top-level service legitimately has no inbound dependency edges) rather than pretending to a precision they cannot have. A "config key read nowhere" rule, for instance, is intentionally absent — it cannot be answered from introspection output without scanning source, which is a different tool.

## Installation

Standalone:

```bash
composer require --dev univeros/suggest
```

You will usually want this as a dev dependency — it advises on your *codebase*, not your runtime. If you install the full framework, `composer require univeros/framework` already bundles it.

It depends on [`univeros/introspection`](./introspection.md) (the inspectors it reads), plus `univeros/cli`, `univeros/configuration`, and `univeros/container`. Apply `IntrospectionConfiguration` alongside `SuggestConfiguration` to get the richest snapshot; without it, Suggest still runs but only sees what it can construct directly.

## Quick start

Walk the project and print a scannable report:

```bash
bin/altair suggest
```

```
[warning] dead_event — Event 'order.shipped' is registered but has no listeners — it is dispatched to nobody.
          fix: Register a listener or remove the event registration.
[info   ] fat_constructor — App\Service\Checkout has 7 constructor dependencies (threshold 5) — consider splitting its responsibilities.
          fix: Extract collaborators into a smaller, focused service or a facade.
[info   ] route_without_spec — Route DELETE /users/{id} has no scaffolder spec — it appears to be hand-wired.
          fix: Capture it as a YAML spec and run `bin/altair spec:scaffold`.

3 suggestion(s) — 1 warning, 2 info — in 6ms
```

Show only the high-confidence findings — this is the CI-gate form:

```bash
bin/altair suggest --severity=warning
```

Emit machine-readable JSON for an agent or a CI step to parse:

```bash
bin/altair suggest --format=json
```

```json
{
    "count": 1,
    "duration_ms": 6,
    "suggestions": [
        {
            "rule": "dead_event",
            "severity": "warning",
            "subject": "order.shipped",
            "message": "Event 'order.shipped' is registered but has no listeners — it is dispatched to nobody.",
            "fix": "Register a listener or remove the event registration."
        }
    ]
}
```

Run a single rule, or everything except one:

```bash
bin/altair suggest --only=dead_event
bin/altair suggest --skip=dead_binding
```

The process exit code is `1` when any warning-level suggestion is shown, otherwise `0`. Advisory `info` findings never fail a build — so `bin/altair suggest --severity=warning` is a drop-in CI gate that fires only on dead events.

> **Host-application boot is required for the richest snapshot.** `bin/altair` only wires CLI discovery (`CliConfiguration`); it does **not** apply `SuggestConfiguration` or `IntrospectionConfiguration` on your behalf. Wire both in *your* entry point — and share the collections the inspectors read (`RouteCollection`, the `EventDispatcher`, the `MiddlewareCollection`) — so the snapshot reflects your real wiring. A library-only checkout simply produces an emptier snapshot and fewer suggestions, never an error.

## Concepts

**Rules are pure functions of a `Snapshot`.** A `SuggestionRule` receives the snapshot and returns `list<Suggestion>`. It never touches the Container, the filesystem, or reflection — so it is deterministic and testable in isolation. A rule that lacks the data it needs (no specs were collected, no pipeline was inspected) returns `[]` rather than guessing.

```php
interface SuggestionRuleInterface
{
    public function name(): string;                       // stable id: 'dead_event', used by --only/--skip
    public function analyse(Snapshot $snapshot): array;    // list<Suggestion>
}
```

**The `Snapshot` is the single, immutable input.** It carries five structural sections — bindings, routes, events, middleware, specs — each empty when the project does not use that subsystem. Bindings are enriched with the facts the rules need: the object-typed constructor dependencies (the edges of the dependency graph) and the interfaces the target implements.

**The `SnapshotFactory` is the only impure component.** It reads each introspection inspector (any of which may be absent) and reflects binding targets to compute dependencies and interfaces. Like the inspectors it wraps, it reflects classes but never constructs them, so it is safe to run against a project whose database is down or whose boot has side effects.

**Suggestions are graded, and the grade is load-bearing.** `Severity::Warning` is for findings the analyser is confident are dead; `Severity::Info` is advisory. The `--severity` flag is a *floor*: `info` (the default) shows everything, `warning` shows only warnings. The report's exit code is `1` if any shown suggestion is a warning.

**Output is deterministic.** `SuggestionReport::toArray()` and `Suggestion::toArray()` emit a fixed key order, omit the optional `fix` when absent, and carry no timestamps. Suggestions are sorted (severity desc, then rule, then subject) before rendering, so two runs over the same snapshot produce byte-identical JSON apart from `duration_ms`.

## The default rules

`SuggestConfiguration` registers these five. Each maps to one of the heuristics in the design issue, and each is independently swappable.

| Rule | Severity | Flags | False-positive surface |
|---|---|---|---|
| `dead_event` | warning | An event registered in the dispatcher with zero listeners. | None — the dispatcher map literally holds the key with no listeners. |
| `route_without_spec` | info | A runtime route that no scaffolder spec covers. | Silent unless the project uses specs at all (otherwise every route is noise). |
| `orphan_middleware` | info | A PSR-15 middleware bound in the container but absent from the default pipeline. | Hosts with multiple named pipelines; silent when no pipeline was inspected. |
| `fat_constructor` | info | A binding whose constructor pulls in more object collaborators than the threshold (default 5). | A genuinely cohesive class with many small collaborators. |
| `dead_binding` | info | A concrete binding nothing references — no dependency edge, route, pipeline entry, or listener. | Entry points: top-level services, controllers, commands. PSR-15 middleware/handlers and route actions are exempted; the message tells you to verify before deleting. |

A `config_dead_env` rule (flagging environment keys read nowhere) is intentionally **not** shipped: it cannot be answered from introspection output without scanning source code, which is out of scope for a snapshot-based analyser.

## Usage

### Running programmatically

The engine and the snapshot factory are the entry points — the CLI command is a thin wrapper over them:

```php
use Altair\Suggest\Snapshot\SnapshotFactory;
use Altair\Suggest\SuggestionEngine;
use Altair\Suggest\Result\Severity;

/** @var SnapshotFactory $factory */          // resolve after SuggestConfiguration::apply()
/** @var SuggestionEngine $engine */
$factory = $container->make(SnapshotFactory::class);
$engine  = $container->make(SuggestionEngine::class);

$report = $engine->analyse(
    $factory->create(),
    Severity::Info,        // minimum severity floor
    only: [],              // rule names to run exclusively
    skip: ['dead_binding'],
);
```

### Reading the report

```php
$report->suggestions;                       // list<Suggestion>, already filtered and sorted
$report->exitCode();                        // 1 if any warning, else 0
$report->countBy(Severity::Warning);        // int

$data = $report->toArray();
// ['count' => 1, 'duration_ms' => 6, 'suggestions' => [ ... ]]

foreach ($report->suggestions as $s) {
    $s->rule;        // 'dead_event'
    $s->severity;    // Severity::Warning
    $s->subject;     // the binding id / route / event the suggestion is about
    $s->message;     // human-readable finding
    $s->fix;         // ?string — the next-action hint
}
```

### Writing and registering a custom rule

A rule is one small class. Here is one that flags any binding whose id ends in `Manager` as a naming smell — note it reads only the snapshot:

```php
<?php

declare(strict_types=1);

namespace App\Suggest;

use Altair\Suggest\Contracts\SuggestionRuleInterface;
use Altair\Suggest\Result\Severity;
use Altair\Suggest\Result\Suggestion;
use Altair\Suggest\Snapshot\Snapshot;
use Override;

final readonly class ManagerNamingRule implements SuggestionRuleInterface
{
    #[Override]
    public function name(): string
    {
        return 'manager_naming';
    }

    #[Override]
    public function analyse(Snapshot $snapshot): array
    {
        $out = [];
        foreach ($snapshot->bindings as $binding) {
            if (str_ends_with($binding->id, 'Manager')) {
                $out[] = new Suggestion(
                    rule: $this->name(),
                    severity: Severity::Info,
                    subject: $binding->id,
                    message: $binding->id . ' is a "Manager" — consider a more specific name.',
                );
            }
        }

        return $out;
    }
}
```

Register it on the `RuleRegistry`, typically via a Container `prepare` hook after `SuggestConfiguration` has populated the defaults:

```php
use Altair\Container\Container;
use Altair\Suggest\RuleRegistry;
use App\Suggest\ManagerNamingRule;

$container->prepare(
    RuleRegistry::class,
    static fn(RuleRegistry $registry) => $registry->add(new ManagerNamingRule()),
);
```

## Configuration

`SuggestConfiguration` wires the rule registry, the snapshot factory, the engine, and the renderer registry into the Container in one `apply()` call:

```php
use Altair\Suggest\Configuration\SuggestConfiguration;

(new SuggestConfiguration(
    fatConstructorThreshold: 5,   // the only knob; > this many object deps trips fat_constructor
))->apply($container);
```

The snapshot factory resolves each introspection inspector lazily and defensively: an inspector that is not bound — or that cannot construct because its underlying collection is absent — is treated as null, and that snapshot section comes back empty. So the Configuration applies cleanly whether or not the host uses FastRoute, Happen, Relay, or the spec scaffolder. The `ContainerInspector` is constructed against the real container the Configuration is handed (a `Container`-typed delegate parameter would otherwise be auto-wired to a fresh, empty instance), so the binding view always reflects your actual container.

### Output formats

`RendererRegistry::default()` ships `human` and `json`. To add your own, bind a populated registry before bootstrapping:

```php
use Altair\Suggest\Output\HumanRenderer;
use Altair\Suggest\Output\JsonRenderer;
use Altair\Suggest\Output\RendererRegistry;

$container->delegate(
    RendererRegistry::class,
    static fn(): RendererRegistry => new RendererRegistry([
        'human'    => new HumanRenderer(),
        'json'     => new JsonRenderer(),
        'markdown' => new App\Suggest\MarkdownRenderer(),
    ]),
);
```

An unknown `--format` (or `--severity`) exits `2` with a message listing the valid values.

## Testing

The published tests under `tests/Suggest/` double as worked examples of every extension point. Because rules are pure, every rule test hand-builds a `Snapshot` and asserts on the suggestions — no Container, no reflection, no filesystem:

```php
use Altair\Suggest\Rule\DeadEventRule;
use Altair\Suggest\Snapshot\EventNode;
use Altair\Suggest\Snapshot\Snapshot;
use Altair\Suggest\Result\Severity;

$snapshot = new Snapshot(events: [new EventNode('order.placed', 0)]);

$suggestions = (new DeadEventRule())->analyse($snapshot);

self::assertSame(Severity::Warning, $suggestions[0]->severity);
self::assertSame('order.placed', $suggestions[0]->subject);
```

- [tests/Suggest/Rule/](../../tests/Suggest/Rule/) — one focused test per rule, each over hand-built snapshots.
- [tests/Suggest/SuggestionEngineTest.php](../../tests/Suggest/SuggestionEngineTest.php) — the engine: aggregation, the `--severity` floor, `--only`/`--skip`, deterministic ordering.
- [tests/Suggest/Snapshot/SnapshotFactoryTest.php](../../tests/Suggest/Snapshot/SnapshotFactoryTest.php) — the one integration test: builds real inspectors over fixtures and asserts the reflected dependency/interface enrichment.
- [tests/Suggest/Output/RenderersTest.php](../../tests/Suggest/Output/RenderersTest.php) — human + JSON rendering, determinism of the JSON projection.
- [tests/Suggest/Cli/SuggestCommandTest.php](../../tests/Suggest/Cli/SuggestCommandTest.php) — exit codes, format/severity handling, flag forwarding.
- [tests/Suggest/Configuration/SuggestConfigurationTest.php](../../tests/Suggest/Configuration/SuggestConfigurationTest.php) — Container wiring and graceful degradation without introspection.

When you add a rule, mirror this: construct a `Snapshot` with exactly the nodes the rule reasons about, and assert on the resulting suggestions. No rule should require a real Container or a booted app to test.

## Extending

The two natural extension points are the rule set and the renderer set.

**A new rule** implements `SuggestionRuleInterface` and is `add()`-ed to the `RuleRegistry`, as shown in [Usage](#writing-and-registering-a-custom-rule). Keep it a pure function of the `Snapshot` — if you find yourself wanting to reflect a class or read a file inside a rule, that data belongs in the `SnapshotFactory` (extend the `Snapshot` model with the structural fact, gather it once) so every rule can share it and stay testable.

**A new renderer** implements `SuggestionRendererInterface` and is registered in a `RendererRegistry` under its `--format` key. The contract requires determinism — same `SuggestionReport`, byte-identical output (the `duration_ms` aside) — so avoid `microtime()` and unordered iteration.

## Related packages

- [`univeros/introspection`](./introspection.md) — the "what is?" primitive Suggest reads. Suggest is the "what should be?" companion: it consumes the inspectors' output rather than re-deriving it.
- [`univeros/doctor`](./doctor.md) — the sibling adviser. Doctor answers "is this project *healthy*?" (correctness: PHP, extensions, tests, drift); Suggest answers "is this project *well-shaped*?" (refactors: dead code, fat constructors). Both emit deterministic JSON and gate CI by exit code.
- [`univeros/scaffold`](./scaffold.md) — `route_without_spec` nudges hand-wired routes back under `spec:scaffold`, where the OpenAPI fragment, the test, and the SDK stay in sync.
- [`univeros/cli`](./cli.md) — `SuggestCommand` is a plain invokable registered through `#[Command(name: 'suggest')]`; `--format`/`--severity`/`--only`/`--skip` are `#[Option]`s.
- [`univeros/container`](./container.md) — resolves the engine, factory, and renderers; the `ContainerInspector` reads its binding collections to build the dependency graph.

## Limitations

- **It reads the runtime surface, not your source.** Every signal comes from introspection. A rule that needs source-level facts (which env keys are read, which methods are called) cannot be expressed here — that is a static-analysis tool's job. This is why `config_dead_env` is not shipped.
- **`dead_binding` has an inherent false-positive surface.** Top-level services, controllers, and commands legitimately have no inbound dependency edges. The rule exempts PSR-15 middleware/handlers and route actions, grades itself `info`, and tells you to verify — but it cannot know about every framework entry-point convention. `--skip=dead_binding` if it is too noisy for your app.
- **`orphan_middleware` sees one pipeline.** The snapshot captures the default `MiddlewareCollection`. A middleware used only by a secondary named pipeline will look orphaned; hence `info`, not `warning`.
- **The snapshot is only as rich as the host wiring.** Routes, events, middleware, and specs sections are empty unless the corresponding inspectors (and their backing collections) are bound. `bin/altair suggest` from a bare framework checkout, with no host applying `SuggestConfiguration` + `IntrospectionConfiguration`, sees little — wire both in your application's entry point.
- **No MCP tool yet.** The deterministic JSON is MCP-ready, but a `framework__suggest` tool wrapper (the way [`univeros/mcp`](./mcp.md) wraps doctor and the inspectors) is a planned follow-up, not part of this package today.
