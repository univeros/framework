# Altair Framework documentation

Per-package guides for the sub-packages bundled in `univeros/framework`. Each page stands alone — a developer who lands on it via search should be productive with the package after reading it, without opening the source.

The framework targets PHP 8.3+, follows PSR-7/15/14/6/16 where applicable, and is composed of independently usable packages. You can install the meta-package (`composer require univeros/framework`) or pick the pieces you need (`composer require univeros/cookie`, etc.).

## Packages

### HTTP stack

The request/response lifecycle and everything that runs inside it.

- [Http](./packages/http.md) — PSR-15 middleware pipeline with the framework's signature Action / Domain / Input / Responder request lifecycle, FastRoute integration, and JWT / basic / digest auth middleware.
- [Cookie](./packages/cookie.md) — readonly value objects for `Cookie` / `Set-Cookie`, plus a manager that round-trips them through PSR-7 messages.
- [Session](./packages/session.md) — server-side session storage with File / Mongo / PDO / Predis handlers, paired with the cookie envelope and HTTP cache limiters.
- [Sanitation](./packages/sanitation.md) — sixteen input filters (Alpha, Boolean, Integer, Regex, …) that normalise raw values into safe canonical forms before validation runs.
- [Validation](./packages/validation.md) — eighteen rule-based input validators (Email, IBAN, ZipCode, …) composable into rule collections and runnable through a `Validator`.
- [Idempotency](./packages/idempotency.md) — Stripe-style `Idempotency-Key` primitive: a PSR-15 middleware that hashes the body, claims the key in a pluggable store (InMemory / APCu / Redis), replays the captured response on retry, and refuses 409 on payload drift. Spec-driven via an `idempotency:` block and an `x-altair-idempotency` OpenAPI extension that round-trips.
- [Webhooks](./packages/webhooks.md) — first-class webhook primitive, both directions: inbound signature verification (HMAC-SHA256/512, Ed25519) + timestamp window + event-id dedupe as PSR-15 middleware, and an outbound signed dispatcher over Symfony Messenger with retry, dead-letter, and `bin/altair webhook:replay`. Spec-driven via a `webhook:` block and an `x-altair-webhook` OpenAPI extension that round-trips.

### Application core

Cross-cutting building blocks the rest of the framework — and your app — wire into.

- [Container](./packages/container.md) — runtime auto-wiring DI container with generic-typed resolution, fluent bindings, attribute autowiring, contextual + tagged + lazy services, decorators, child scopes, and reflection caching.
- [Configuration](./packages/configuration.md) — composable configuration objects with phpdotenv 5 environment loading and lazy container-bound resolution.
- [Happen](./packages/happen.md) — PSR-14 event dispatcher with priorities, subscribers, named and wildcard listeners, and stoppable events.
- [Courier](./packages/courier.md) — command bus with a middleware pipeline, container-backed handler resolution, and re-entrant dispatch handling.
- [Middleware](./packages/middleware.md) — the framework's generic (non-HTTP) middleware contract — `Payload` + `Runner` + `MiddlewareManager` — used internally by Sanitation, Validation, and Courier.

### Data & types

Typed values, attribute traits, and the collection primitives many of the other packages build on.

- [Data](./packages/data.md) — entity attribute traits, `JsonSerializable` and arrayable bridges, and date attribute mutators.
- [Structure](./packages/structure.md) — typed data structures in pure PHP — `Map`, `Set`, `Vector`, `Queue`, `Stack`, `Deque`, `PriorityQueue`, `Pair`.
- [Common](./packages/common.md) — small grab-bag of pure utilities — `Str`, `Arr`, `Inflector`, `Pluralizer`, `Transliterator`, and a stateful `ArrayRegistry`.

### Infrastructure

Talking to external systems and managing cryptographic primitives.

- [Cache](./packages/cache.md) — PSR-6 cache item pool and PSR-16 simple cache, backed by Filesystem, Memcached, Redis, or Predis storage.
- [Filesystem](./packages/filesystem.md) — Flysystem v3 wrapper with Local, S3, FTP, SFTP, and Dropbox adapters, plus a convenience decorator.
- [Messaging](./packages/messaging.md) — thin `MessageBus` + worker bridge over Symfony Messenger, with attribute-driven handler discovery, `bin/altair worker*` commands, and an optional `queue:` block on endpoint specs.
- [Persistence](./packages/persistence.md) — thin Repository / UnitOfWork contract over Cycle ORM v2, with `bin/altair db:*` migration commands and an optional `persistence:` block on endpoint specs.
- [Security](./packages/security.md) — cryptographic primitives — HKDF / PBKDF2 key derivation, an encryption envelope with double-HMAC MAC, and salt generation.

### Tooling

Developer experience and AI-agent ergonomics.

- [Cli](./packages/cli.md) — attribute-driven CLI: write a `#[Command]` invokable, decorate its `__invoke()` params with `#[Argument]`/`#[Option]`, and `bin/altair` discovers and autowires it. The substrate every other tool's commands ride on.
- [AgentSpec](./packages/agent-spec.md) — turns every framework package into a deterministic Markdown manifest under `.agent/` so AI agents can be productive without reading source. Ships `manifest:generate` and `manifest:show` CLI commands with a `--check` drift gate for CI.
- [Scaffold](./packages/scaffold.md) — the spec-driven core: a YAML endpoint spec in, Action / Input / Responder / domain stub / test / OpenAPI fragment / route entry out, plus a rewind/replay journal, drift linting, and TypeScript/Python SDK emitters.
- [Introspection](./packages/introspection.md) — a read-only X-ray of a booted app — container bindings, routes, listeners, middleware, specs, masked config — as `bin/altair` commands with `--format=json` for agents.
- [Doctor](./packages/doctor.md) — a health-check runner (`bin/altair doctor`) with a deterministic JSON report: PHP/extension/composer checks, CS/PHPStan/test gates, container and database probes, and opt-in fixes.
- [Suggest](./packages/suggest.md) — a refactor adviser (`bin/altair suggest`) that walks the introspection surface and flags dead bindings/events, fat constructors, routes without specs, and orphan middleware as swappable rules, with deterministic JSON for agents and CI.
- [Index](./packages/index.md) — a symbol-usage index (`bin/altair index:*`) built from the PHP AST plus spec awareness, answering find-usages, implementers, callers-of, dead-code, and refactor-impact queries in milliseconds. SQLite-backed with incremental rebuilds; deterministic JSON for agents and CI.
- [Eval](./packages/eval.md) — `bin/altair eval '<php>'` runs a snippet in a sandboxed subprocess against the project container (disable_functions, open_basedir, memory + wall-clock kill) and returns a structured JSON result. The agent's "let me check" primitive; `--unsafe` lifts every guard and is audit-logged to events.jsonl.
- [Profiling](./packages/profiling.md) — `bin/altair profile:*` — framework-native sampling profiler (ext-excimer). Captures a weighted call tree + hotspot table + flamegraph SVG, and `profile:compare` diffs two runs to flag regressions (CI gate). Closes the optimisation loop the way `index:impact` closed the refactor loop.
- [Observability](./packages/observability.md) — `bin/altair observability:*` — OpenTelemetry-format runtime tracing + metrics. PSR-15 middleware records per-request spans, a JSONL log captures them locally, and an OTLP-JSON exporter forwards to any OTel Collector — no OTel SDK dependency. Tail / stats / one-shot export from CLI and MCP.
- [Migration Intelligence](./packages/migration-intelligence.md) — `bin/altair db:migration-plan` proposes a safe Cycle migration from a spec/entity diff, with read-only safety checks (NOT NULL backfill, unique dupes, FK orphans, type-cast, large tables), two-phase rename plans, and per-dialect preview SQL.
- [Events](./packages/events.md) — the append-only `.altair/events.jsonl` mutation log, so agents and developers can answer "what just changed?" across sessions. (Not the PSR-14 dispatcher — that's Happen.)
- [TestReporter](./packages/test-reporter.md) — an AI-native PHPUnit 11 extension that emits a structured JSON report, mapping each failure back to the production source under test with structured diffs.
- [Mcp](./packages/mcp.md) — a first-party Model Context Protocol server exposing the framework as 42 agent-callable tools over stdio/HTTP. The **shell-less bridge** for hosted agents, non-Claude MCP clients, and remote introspection of deployed APIs — shell-capable agents with a local checkout should reach for the **Altair agent skill** (shipped in every generated project at `.ai/skills/altair/SKILL.md` and `.claude/skills/altair/SKILL.md`) + `bin/altair` instead.
- [Bootstrap](./packages/bootstrap.md) — zero-to-running project bootstrap: `bin/altair new` materialises a complete, runnable Altair API (a working `/ping`, a passing test, the spec-driven toolchain wired) from the skeleton template, with minimal/standard/full presets.
- [Observatory](./packages/observatory.md) — a dev-only, fail-closed web monitoring panel: health, activity (live SSE tail), queues, routes, container, config and migrations, as a thin presentation layer over the framework's own introspection / doctor / events data.
- [Tinker](./packages/tinker.md) — `bin/altair tinker` drops into an interactive PsySH REPL with the DI container in scope and a doctor-style preamble of what's wired; a human debugging tool (dev-only dependency), the dynamic counterpart to introspection.
- [Examples](./packages/examples.md) — a curated, browsable library of idiomatic Univeros patterns under `.altair/examples/`. CLI (`bin/altair examples:list|show|search|index|test`) and MCP tools (`framework__list_examples`, `framework__read_example`, `framework__search_examples`) so agents can discover canonical patterns instead of inventing them. Every example links to a real test, so drift fails CI.

## How these docs are structured

Every package page follows the same skeleton: a one-sentence pitch, an introduction in prose, installation, quick start, concepts, usage, testing notes, optional recipes, and links to related packages. Code examples are runnable against the package's published API and are kept in sync with the source as part of the same PR.

If you spot a drift between the documented behaviour and what the code does, please open an issue at https://github.com/univeros/framework/issues — the source is the source of truth, but the docs are a contract too.
