---
name: altair
description: Drive a Univeros / Altair PHP project through `bin/altair` and `.agent/` manifests instead of reading source. Use when the user asks to scaffold an endpoint from a YAML spec, inspect routes / container / config / listeners / middleware, run doctor health checks, tail the events log, apply or roll back database migrations, manage Messenger workers, or undo a previous scaffold via the journal.
---

# Altair — driving the framework through `bin/altair`

This project is built on the Univeros / Altair PHP framework. The framework is designed to be **agent-operable from the shell** — the CLI is the primary surface, `.agent/` manifests are the read-without-source surface, and almost every command supports `--format=json` so its output is consumable by an agent without parsing prose.

Use the CLI flows below **before** reading source. Read source only when a flow doesn't cover what you need.

> **When to skip this skill:** if the only available channel is MCP (no shell, no local checkout), point the user at `bin/altair mcp:serve` and the `docs/packages/mcp.md` guide — that's the shell-less / remote bridge, and it surfaces the same capabilities as tool calls instead of subprocesses.

## Read the project before mutating it

Two reads to do up front, every session, before the first edit:

1. **`AGENT.md`** at the repo root — the canonical project guide (architecture, package layout, conventions, the modernization roadmap).
2. **`.agent/MANIFEST.md`** — the auto-generated package index. Each `.agent/packages/<slug>.md` is a deterministic Markdown manifest for one package: classes, interfaces, public methods, configuration, "tests as documentation" list. It's a stable substitute for reading source when you only need the API shape.

If `.agent/` is missing or stale: `bin/altair manifest:generate`. CI's determinism gate fails on drift, so don't hand-edit those files.

## Adding an HTTP endpoint (the core flow)

The framework's most opinionated workflow. You **do not hand-write** the Action / Input / Responder / Domain stub / test / OpenAPI fragment / route entry — you write a YAML spec at `api/<resource>/<action>.yaml` and let `spec:scaffold` emit all seven artifacts.

```bash
# 1. Write the spec (a tight YAML describing endpoint + input + output + domain).
$EDITOR api/users/create.yaml

# 2. Preview the emit without touching disk.
bin/altair spec:scaffold api/users/create.yaml --dry-run

# 3. Emit. The command is idempotent: existing files are skipped unless --force.
bin/altair spec:scaffold api/users/create.yaml

# 4. After hand-editing a generated file, check for drift.
bin/altair spec:lint

# 5. Each scaffold records a journal entry under .altair/journal/.
bin/altair journal:list -n 5
bin/altair journal:show <id>
bin/altair journal:rewind            # undo the most recent scaffold
bin/altair journal:rewind --to=<id>  # undo back to a point
```

A `persistence:` block on the spec additionally emits a Cycle-annotated entity, a domain repository, and a Cycle migration. A `queue:` block additionally emits a readonly DTO message, a `#[AsHandler]`-decorated handler stub, and a handler test.

**Never** scaffold by hand-writing the four-class triple — the journal won't see the change and `spec:lint` will report drift. If you must edit a generated file, treat the YAML as the source of truth and prefer re-running `spec:scaffold --force`.

## Inspecting a booted project (read-only X-ray)

When you'd reach for `grep` to figure out what's wired, reach for these first. Every command supports `--format=json` so you can branch on structured output.

```bash
# Container — what's bound to what, and how?
bin/altair container:inspect --format=json

# HTTP routes the dispatcher knows about.
bin/altair routes:list --format=json
bin/altair routes:show GET /users/{id}

# PSR-14 listeners on the Happen dispatcher.
bin/altair listeners:list --format=json
bin/altair listeners:show user.created

# PSR-15 middleware in the queue, in order.
bin/altair middleware:list --format=json

# Config values with secrets masked.
bin/altair config:dump --format=json

# Endpoint specs (YAML under api/).
bin/altair spec:list
bin/altair spec:show api/users/create.yaml

# Drift between the committed .agent/ manifests and the live source.
bin/altair manifest:diff
```

Prefer the introspection JSON over a source grep when the question is "what does this app look like at boot time?" — it reflects the *resolved* configuration, not just the declared one.

## Health checks before committing

```bash
bin/altair doctor                 # human-readable
bin/altair doctor --format=json   # agent-readable deterministic report
bin/altair doctor --fix           # apply offered fixes (idempotent ones)
```

`doctor` covers PHP / extension / composer state, the CS / PHPStan / test gates, container probes, database reachability, **and the #74 determinism gate** (regenerates `.agent/`, diffs, fails on drift). The JSON shape is stable, sorted, no timestamps — safe to compare across runs in CI.

If you're about to commit, run `composer qa` (cs + stan + test). If something fails, run `bin/altair doctor` to see whether it's environment, code, or determinism drift.

## Database migrations

```bash
bin/altair db:migrate                # apply pending
bin/altair db:migrate --dry-run      # list without applying
bin/altair db:migrate:status         # exit 1 if any pending (CI gate)
bin/altair db:migrate:rollback       # undo last; --steps=N for more

# Propose a safe migration from a spec/entity diff, with safety checks.
bin/altair db:migration-plan --rename old:new
```

`db:migration-plan` runs read-only checks against the live DB (NOT NULL backfill, unique dupes, FK orphans, type-cast heuristic, large-table, drop-column) before emitting the Cycle migration + per-dialect preview SQL. Exits 1 on any error so it's CI-gate-safe.

Never write a migration by hand if the schema came from a spec — the entity + repository + migration drift otherwise.

## Background workers

```bash
bin/altair worker                          # consume every configured transport
bin/altair worker --transports=default,high
bin/altair worker --time-limit=3600 --memory-limit=128M --limit=100
bin/altair worker:show-failed              # list envelopes in the failure transport
bin/altair worker:retry-failed             # re-dispatch
```

When you add a queue dispatch, add a `queue:` block to the YAML spec and re-scaffold — don't hand-write the DTO + handler + test.

## The events log (your mutation history)

Every mutating framework operation (scaffold, migration, rewind, replay, worker consume, …) records into `.altair/events.jsonl`. When the user asks "what just changed?", read this — don't `git log` and don't read source.

```bash
bin/altair events:tail -n 50               # newest 50 (human or --format=json)
bin/altair events:show <id>                # full detail
bin/altair events:since <ulid|timestamp>   # everything since a point
bin/altair events:since-last-success       # what happened since the last OK event
bin/altair events:filter --kind=scaffold,migration --status=fail
bin/altair events:stats                    # counts + total duration
bin/altair events:checkpoint:create feat/x # bookmark current head
bin/altair events:checkpoint:diff feat/x   # events since the bookmark
```

When you complete a piece of work, an `events:since-last-success` is a tighter "what did I do this session?" than the entire shell history.

## Code intelligence — index, suggest, eval

Reach for these when the question is "is this safe to change?" or "is this dead code?".

```bash
bin/altair index:build                                   # SQLite-backed AST index
bin/altair index:find-usages App\\User\\User
bin/altair index:callers-of 'App\\User\\CreateUser::__invoke'
bin/altair index:impact App\\User\\User,App\\User\\CreateUser    # tests + specs affected
bin/altair index:unused --strict                         # CI gate on dead code
bin/altair index:orphans                                 # spec endpoints with no target

bin/altair suggest                                       # dead bindings/events, fat ctors, orphans
bin/altair suggest --format=json --severity=warning      # CI gate

bin/altair eval 'return $container->get(Foo::class);'    # sandboxed scratchpad
bin/altair eval 'return iterator_to_array(...);' --timeout-ms=300
```

`eval` runs in a subprocess with `disable_functions` + `open_basedir` + memory/wall-clock kill. Use it for "let me check" probes; `--unsafe` lifts every guard and is audit-logged into `events.jsonl`.

## Performance — profiling + observability

```bash
bin/altair profile:run path/to/script.php
bin/altair profile:list
bin/altair profile:show <id>
bin/altair profile:compare <baseline> <candidate>        # exit 1 on regression
bin/altair profile:flame <id> > flame.svg

bin/altair observability:tail -n 50 --format=json        # spans + metrics
bin/altair observability:stats                           # p50/p95/p99 + error counts
bin/altair observability:export --endpoint=https://otel/v1
```

Profiling needs `ext-excimer` to capture, but **rendering** (`show`, `flame`, `compare`) only needs PHP. Observability has no SDK dependency — speaks OTLP wire format directly.

## REPL when nothing else works

```bash
bin/altair tinker
# $container is bound; doctor-style preamble prints what's wired
```

The REPL is a **human** tool — don't shell into it in autonomous loops. For one-shot probes use `bin/altair eval '<snippet>'` instead.

## House rules for this skill

1. **`bin/altair` is faster than reading source.** Reach for the CLI first; open `src/` when the CLI can't answer.
2. **`.agent/MANIFEST.md` is faster than `.agent/packages/*.md` is faster than `src/`.** Climb the ladder only as far as you need.
3. **Re-scaffold beats hand-editing.** Treat YAML specs as the source of truth for endpoints, entities, and queue handlers.
4. **`composer qa` before committing.** Use `bin/altair doctor` to diagnose failures.
5. **Don't mutate `.agent/`, `.altair/`, or `vendor/`** directly. They're outputs, not inputs.
6. **MCP is for shell-less clients.** If you have a shell and a checkout, you have everything you need — leave MCP for hosted agents and remote / non-Claude clients.

## When you actually do need to read source

The CLI doesn't cover ergonomic deep-dives into a single class's implementation. When you need that:

- Resolve the FQCN through `.agent/packages/<slug>.md` first to find the file path.
- Then `Read` only the file you need.
- Prefer the contracts (`src/Altair/<Pkg>/Contracts/`) over the concretes — the framework's design enforces small, well-named interfaces.
