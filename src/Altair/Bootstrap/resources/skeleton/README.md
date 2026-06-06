<h1 align="center">Univeros</h1>

<p align="center">
  <em>A PHP framework whose primitives — generators, mutations, introspection — are built so an AI agent can drive it.</em>
</p>

<p align="center">
  <a href="https://github.com/univeros/framework/actions/workflows/ci.yml"><img src="https://github.com/univeros/framework/actions/workflows/ci.yml/badge.svg" alt="CI"></a>
  <a href="https://opensource.org/licenses/MIT"><img src="https://img.shields.io/badge/license-MIT-blue.svg" alt="License: MIT"></a>
  <img src="https://img.shields.io/badge/php-%3E%3D8.3-777BB4.svg" alt="PHP 8.3+">
  <img src="https://img.shields.io/badge/packages-36-success.svg" alt="36 packages">
</p>

---

## What is Univeros?

Univeros is a PHP 8.3+ framework for building APIs. The library codebase lives under the `Altair\*` namespace and ships as 35 composable packages (`univeros/http`, `univeros/scaffold`, `univeros/persistence`, …) plus a meta-package, `univeros/framework`, that bundles them all.

It looks familiar at first — PSR-7/15 HTTP stack, a DI container, a Cycle ORM bridge, a Symfony Messenger bridge, immutable value objects, single-pass middleware. The unusual part is the layer above that: a CLI surface (`bin/altair`) whose every command emits **deterministic JSON** an AI agent can branch on, and a set of primitives — **spec-driven scaffolding, a rewindable journal of mutations, an append-only event log, a symbol-usage index, a doctor, a refactor adviser** — designed so an agent can be productive in the codebase without a human in the loop.

This README is the front door to what makes that work and how to use it.

---

## Why it exists

Most frameworks were designed for a developer who reads documentation and infers patterns, greps for examples and mimics them, remembers project conventions between sessions, and catches their own mistakes by re-reading code.

An AI agent does none of those things reliably. It needs:

1. **Conventions emitted as machine-readable manifests** — not inferred from prose docs.
2. **Generators that are deterministic** — so output can be verified, not just executed.
3. **A clean way to undo a failed iteration** — not "hope you committed before that".
4. **Mutation history that persists across context windows** — so "what did I just do?" is answerable in any session.

Univeros provides those four things as first-class primitives. That is the reason it exists.

---

## Quick start

```bash
composer create-project univeros/univeros myapp
cd myapp
composer serve            # php -S localhost:8080 -t public
curl localhost:8080/ping  # {"message":"ok","timestamp":"..."}
composer test
```

You now have a runnable Univeros API with a passing test, a health endpoint, the spec-driven toolchain wired, and the Altair agent skill staged at `.ai/skills/altair/SKILL.md` and `.claude/skills/altair/SKILL.md` so any agent (Claude Code, Cursor, ChatGPT desktop) that lands in the project gets the project-specific operating manual on first load.

To add an endpoint, write the spec — don't hand-write the boilerplate:

```yaml
# api/users/create.yaml
endpoint:
  name: CreateUser
  method: POST
  path: /users
input:
  email: { type: string, format: email, required: true }
  name:  { type: string, required: true }
responder:
  success: 201
persistence:
  entity: User
  table: users
  columns:
    id:    { type: uuid, primary: true }
    email: { type: string, unique: true }
    name:  { type: string }
```

Then scaffold:

```bash
vendor/bin/altair spec:scaffold api/users/create.yaml
# Writes: Action, Input, Responder, domain stub, PHPUnit test,
#         route entry, OpenAPI fragment, Cycle entity, migration, repository.
```

Every emitted file is byte-stable. Re-run on the same spec and not a byte changes — `bin/altair spec:lint` is a CI drift gate, and the SDK emitters ship their own `--check` mode.

---

## What makes it work for agents

These are the framework primitives that exist because agents need them. Each is a real `bin/altair` command with a `--format=json` (or default JSON) output mode.

### 1. Spec-driven, deterministic emitters

Every code generator — scaffolder, OpenAPI emitter, TypeScript SDK emitter, Python SDK emitter, Cycle migration emitter — produces byte-stable output for the same input. CI gates verify this:

```bash
vendor/bin/altair spec:scaffold api/users/create.yaml          # emit
vendor/bin/altair spec:emit-openapi --out docs/openapi.yaml    # merge OpenAPI fragments
vendor/bin/altair spec:emit-sdk typescript --out=sdk.ts --check  # exit 1 on drift
vendor/bin/altair spec:lint                                    # drift check after hand-edits
```

An agent that generates from a spec can re-generate and compare to verify it did not corrupt the output. Determinism is enforced by issue **#74** as a CI gate on every PR.

### 2. Spec journal — rewind / replay

Every successful `bin/altair spec:scaffold` writes a self-contained entry to `.altair/journal/<id>.json` capturing the spec, per-file SHAs, and `content_before` for modified files. Failed iterations become recoverable.

```bash
vendor/bin/altair journal:list -n 50            # newest 50 entries
vendor/bin/altair journal:rewind                # undo the most recent scaffold
vendor/bin/altair journal:rewind --to=<id>      # undo back to a point
vendor/bin/altair journal:rewind --dry-run      # preview without writing
vendor/bin/altair journal:replay <id>           # re-apply one entry
```

### 3. Append-only event log — cross-session memory

Every mutating operation (scaffold, migration, rewind, replay, `cs:fix`, rector, worker consume, manifest generate) records a structured entry to `.altair/events.jsonl`:

```bash
vendor/bin/altair events:tail -n 50                  # newest 50 events
vendor/bin/altair events:since-last-success          # what happened since the last OK event
vendor/bin/altair events:stats                       # counts by kind/status + total duration
vendor/bin/altair events:checkpoint:create feat/posts # bookmark current head
vendor/bin/altair events:checkpoint:diff feat/posts  # everything since the bookmark
```

An agent opens a fresh session, reads the log, and knows what was attempted, what landed, and what failed — without re-deriving it from `git log`.

### 4. Symbol-usage index — refactor with confidence

`bin/altair index` walks every PHP file with `nikic/php-parser` and stores symbols + usages in SQLite (`.altair/index.db`). It understands the framework's higher-level constructs too — spec endpoints, persistence entities — so refactor queries surface the YAML that drives a class, not just the PHP that references it.

```bash
vendor/bin/altair index:build                                  # ~1.8s on a 1490-file repo (8k symbols, 21k usages)
vendor/bin/altair index:find-usages "App\User\User"
vendor/bin/altair index:implements "Altair\Http\Contracts\MiddlewareInterface"
vendor/bin/altair index:callers-of "App\User\UserRepository::findById"
vendor/bin/altair index:impact "App\Order\Order"               # blast radius of changing this
vendor/bin/altair index:unused                                 # dead symbols
vendor/bin/altair index:orphans                                # files with no incoming references
```

Incremental rebuilds (content-hashed per file) keep queries millisecond-fast on a moving session.

### 5. Doctor — agent-actionable health checks

`bin/altair doctor` runs PHP / extension / composer checks, CS / PHPStan / test gates, container probes, and database probes. The JSON shape includes an `agent_action` per failure, telling the agent exactly what to do next — not just *what is wrong*, but *what to run next to fix it*.

```bash
vendor/bin/altair doctor --format=json
vendor/bin/altair doctor --fix              # opt-in autofix for the subset that's safe
vendor/bin/altair doctor --only=database    # filter by check group
```

### 6. Suggest — refactor adviser

`bin/altair suggest` walks the introspection surface and flags dead container bindings, fat constructors, dead event listeners, routes without specs, orphan middleware. Each finding ships with file, line, rationale, and (where safe) an autofix.

```bash
vendor/bin/altair suggest --format=json
vendor/bin/altair suggest --rule=dead-bindings
```

### 7. Eval — sandboxed scratchpad

`bin/altair eval '<php>'` runs a snippet inside the project's container in a guarded subprocess (`disable_functions`, `open_basedir`, memory + wall-clock kill) and returns a structured JSON result.

```bash
vendor/bin/altair eval 'return $container->get(App\User\UserRepository::class)->count();'
```

The agent's "let me check" primitive. `--unsafe` lifts the sandbox and is audit-logged to `events.jsonl`.

### 8. MCP server — drive Univeros from any agent

`bin/altair mcp:serve` exposes 42 framework operations as Model Context Protocol tools over stdio or HTTP. Any MCP-capable agent (Claude Code, ChatGPT desktop, Cursor) can drive a Univeros project natively — introspect routes, run doctor, scaffold endpoints, rewind journal entries, query the index — without shell access.

```bash
vendor/bin/altair mcp:serve            # stdio
vendor/bin/altair mcp:serve --http     # HTTP for remote agents
vendor/bin/altair mcp:tools            # list exposed tools
```

For shell-capable agents working in a local checkout, the framework also ships the Altair skill at `.ai/skills/altair/SKILL.md` (and `.claude/skills/altair/SKILL.md`) — onboarding without prompting.

### 9. Introspection — read the booted app as JSON

`bin/altair` ships read-only inspectors for everything the container resolves: bindings, routes, listeners, middleware, manifests, specs, masked config. Each emits deterministic JSON for agents and a scannable view for humans.

### 10. Test reporter — failures mapped back to source

`Altair\TestReporter\AltairExtension` is a PHPUnit 12 extension that emits a structured JSON report at the end of every run — failures mapped back to the production class under test (via `#[CoversClass]`, `@covers`, or a namespace heuristic), structured diffs for `assertSame` / `assertEquals`, one-word `result` for agents to branch on.

### 11. Migration intelligence — safe schema changes

`bin/altair db:migration-plan` proposes a Cycle migration from a spec/entity diff, with read-only safety checks: NOT NULL backfill paths, unique-constraint dupes, FK orphans, type-cast risk, large-table warnings, two-phase rename / type-change plans. Deterministic JSON output for agents and CI.

---

## What you get on the human side

Concrete decisions worth highlighting if you're evaluating from a developer angle, not an agent angle:

- **PHP 8.3+**, `declare(strict_types=1)` on every file (995/995 source files compliant), native types over PHPDoc.
- **PSR-7 / 15 / 14 / 6 / 16** where applicable.
- **Single-pass middleware** — no `$next($req, $res)` double-pass leftovers from the 2015 era.
- **Immutable value objects** — `withFoo()` instead of `setFoo()`. See `Altair\Cookie\Cookie` as the reference.
- **Cycle ORM v2** behind framework-owned `RepositoryInterface` / `UnitOfWorkInterface` / `EntityManagerInterface` contracts — ORM imports stay out of HTTP/domain code.
- **Symfony Messenger** behind a thin `MessageBusInterface` bridge, attribute-driven handler discovery (`#[AsHandler]`), built-in `vendor/bin/altair worker` commands.
- **Attribute-driven CLI** — write a `#[Command]` invokable, decorate its `__invoke()` params with `#[Argument]`/`#[Option]`, and `bin/altair` discovers and autowires it.
- **OpenTelemetry-format observability** without an OTel SDK dependency: PSR-15 middleware emits OTLP-JSON spans, the JSONL log captures them locally, the OTLP exporter forwards to any OTel Collector.
- **Sampling profiler** (`vendor/bin/altair profile:*`, ext-excimer) — weighted call tree, hotspot table, flamegraph SVG, `profile:compare` regression gate for CI.
- **Many small files** — 200-400 LOC typical, 800 LOC hard cap. Cohesion over containerisation.

---

## Architecture in three layers

```
┌──────────────────────────────────────────────────────────────┐
│  HTTP                                                        │
│  Http · Middleware · Cookie · Session · Sanitation           │
│  Validation · Courier · Security                             │
├──────────────────────────────────────────────────────────────┤
│  Application core                                            │
│  Container · Configuration · Happen · Common · Structure     │
│  Data · Cache · Filesystem · Messaging · Persistence         │
├──────────────────────────────────────────────────────────────┤
│  Agent and tooling surface                                   │
│  Scaffold · AgentSpec · Cli · Introspection · Doctor         │
│  Suggest · Index · Eval · Profiling · Observability          │
│  Migration Intelligence · Events · TestReporter              │
│  Mcp · Bootstrap · Tinker · Observatory                      │
└──────────────────────────────────────────────────────────────┘
```

All three layers ship as the bundled `univeros/framework`, or as 35 independently installable packages.

---

## Where everything lives

- **[univeros/univeros](https://github.com/univeros/univeros)** — this repo. The `composer create-project` starter.
- **[univeros/framework](https://github.com/univeros/framework)** — the library source code (`Altair\*` namespace, ships as `composer require univeros/framework`). All issues and pull requests belong here.
- **[univeros/docs](https://github.com/univeros/docs)** — per-package guides.

> This repository is a read-only mirror of `src/Altair/Bootstrap/resources/skeleton/` in [univeros/framework](https://github.com/univeros/framework). Open issues and pull requests there.

---

## Contributing

Bug reports and pull requests against [univeros/framework](https://github.com/univeros/framework). The 35 sub-package repos under `github.com/univeros/*` are read-only mirrors maintained automatically by the framework's split workflow.

## Security

If you discover a security vulnerability, please **report it privately via [GitHub Security Advisories](https://github.com/univeros/framework/security/advisories/new)** instead of opening a public issue. All vulnerabilities will be promptly addressed.

## License

Univeros is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
