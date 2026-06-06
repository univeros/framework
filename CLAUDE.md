# CLAUDE.md

Claude Code entry point for the **Univeros / Altair Framework** (`univeros/framework`, PHP 8.3+).

> **The canonical project guide is [AGENT.md](AGENT.md).** Read it first.
> This file adds only Claude-Code-specific guidance on top.

---

## 1. Quick orientation

- **What it is:** A monorepo PHP framework with 40 sub-packages under `src/Altair/*`, bundled via composer `replace`.
- **Where conventions live:** [AGENT.md](AGENT.md) §5 (coding style), §6 (testing).
- **Modernization is complete:** [AGENT.md](AGENT.md) §7 records the (finished) PHP 7.2 → 8.3 migration — Phases 1–4 all done, PHPStan level 8 with no baseline. There is no pending modernization work; new work lives in the open GitHub issues.

If you're being asked to make a change, read the relevant sub-package's `Contracts/` directory before its concrete classes.

---

## 2. Commands you can run

```bash
composer install
composer update -W

composer qa            # cs + stan + test — the pre-commit gate
composer test          # PHPUnit 12
composer cs            # PHP-CS-Fixer dry-run
composer cs:fix        # apply
composer stan          # PHPStan
composer rector        # Rector dry-run (PHP 8.3 + code-quality sets)
composer rector:fix    # apply Rector
```

CI mirrors these: `.github/workflows/ci.yml`.

**Note:** PHP may not be installed on the user's local machine. If `composer`/`php` aren't on PATH, **say so explicitly** rather than claiming success. Don't fabricate test output.

---

## 3. Claude-Code-specific behavior

### Agents to use proactively

| Situation | Subagent |
|---|---|
| Bulk search across the ~1,100 source files | `Explore` |
| Designing a new sub-package or refactor spanning several files | `architect` then `planner` |
| Writing new code or fixing bugs | `tdd-guide` (write the failing test first) |
| Immediately after code changes | `code-reviewer` |
| Before any commit touching auth/security/crypto/input parsing | `security-reviewer` |
| Build/composer/type errors | `build-error-resolver` |
| Removing unused code | `refactor-cleaner` |
| Updating this file or AGENT.md after structural changes | `doc-updater` |

### Skills to use

- `/code-review` after writing code (security + quality + style)
- `/security-review` before committing anything touching `Altair\Security\*`, `Altair\Session\*`, `Altair\Http\Middleware\Csrf*`, `Altair\Cookie\*`, or JWT handling
- `/verify` to run the full QA pipeline (cs + stan + test)
- `/refactor-clean` for dead-code passes
- `/tdd` for new features — tests first, implementation second

### Generating HTTP endpoints

The `univeros/scaffold` sub-package emits Action / Input / Responder / domain stub / PHPUnit test / OpenAPI fragment / route entry from a single YAML spec.

```bash
bin/altair spec:scaffold api/users/create.yaml          # write files
bin/altair spec:scaffold api/users/create.yaml --dry-run
bin/altair spec:scaffold api/ --force                   # batch + overwrite
bin/altair spec:emit-openapi --out docs/openapi.yaml    # merge fragments
bin/altair spec:lint                                    # drift check
```

When you add a new HTTP endpoint, write the YAML spec first and scaffold it — don't hand-write the Action/Input/Responder triple. After hand-editing generated files, run `bin/altair spec:lint` so drift surfaces in CI.

### Modules (pluggable extensions)

The `univeros/module` sub-package lets a third-party package self-register a whole feature into a host app. A module is a class implementing `Altair\Module\Contracts\ModuleInterface` (a `ConfigurationInterface` + `name()`); it opts into capabilities by also implementing `RoutesProviderInterface`, `EntityDirectoriesProviderInterface`, and/or `MigrationDirectoriesProviderInterface`. The host registers it in `config/modules.php`; `ModuleConfiguration` tags each module `altair.module`, and the front controller (`Altair\Http\Support\ModuleRoutes`), the schema provider (`ModuleAwareSchemaProvider`), and the `db:migrate` commands (Cycle `vendorDirectories`) pick the contributions up.

```bash
bin/altair module:new --dir=user-management --name=acme/user-management   # scaffold a module package
bin/altair module:new --name=acme/billing --namespace='Acme\Billing'      # explicit namespace
```

When building an extension for Univeros, **scaffold it with `module:new`** and fill in the generated `Module.php` — don't hand-roll the wiring. Routes and migrations are picked up automatically; module entities need the host to bind `SchemaProviderInterface` to `ModuleAwareSchemaProvider`. Use your own vendor namespace — never `Altair\*` or a `univeros/*` name. Full guide: [docs/guides/extending.md](docs/guides/extending.md).

### Client SDK emitters

`bin/altair spec:emit-sdk typescript|python` turns the merged OpenAPI 3.1 document into a typed client SDK — no external code-gen runtime (the doc is parsed with `symfony/yaml` into a neutral model under `Altair\Scaffold\Sdk\Model`, then each emitter walks it).

```bash
bin/altair spec:emit-sdk typescript > sdk.ts            # fetch-based, zero-dep, discriminated unions
bin/altair spec:emit-sdk python --out=clients/python    # httpx + pydantic v2, sync + async clients
bin/altair spec:emit-sdk typescript --out=sdk.ts --check  # CI drift gate (exit 1 on drift)
bin/altair spec:emit-sdk --list
```

Output is deterministic — wire it into CI with `--check` so the committed SDK can't drift from the spec. Don't hand-edit emitted SDKs; regenerate. To add a language, implement `Altair\Scaffold\Sdk\Contracts\EmitterInterface` and register it in `EmitterRegistry` — don't fork the TypeScript/Python emitters.

### Persistence (Cycle ORM bridge)

The `univeros/persistence` sub-package wraps Cycle ORM v2 behind framework-owned `RepositoryInterface` / `UnitOfWorkInterface` / `EntityManagerInterface` contracts. The host application binds a `SchemaProviderInterface` (either build-time pre-compiled or `AttributeSchemaProvider`), then `CycleOrmConfiguration` wires the rest from `DB_*` env vars.

Adding a `persistence:` block to a spec extends the scaffolder to also emit a Cycle-annotated entity, a domain-specific repository, and a Cycle migration file. See [AGENT.md §2](AGENT.md#2-repository-layout) for sub-package layout.

```bash
bin/altair db:migrate                # apply pending migrations
bin/altair db:migrate --dry-run      # list pending without applying
bin/altair db:migrate:rollback       # roll back last migration (--steps=N)
bin/altair db:migrate:status         # list applied/pending (exit 1 if any pending)
bin/altair db:schema-sync --entities=app/User,app/Order   # DEV ONLY — never in prod
```

When adding a persisted entity, write the spec's `persistence:` block first; don't hand-write entity + migration + repository — they drift otherwise. Treat repositories as the only place that constructs Cycle queries; keep Cycle imports out of HTTP/domain code.

### Messaging (Symfony Messenger bridge)

The `univeros/messaging` sub-package wraps Symfony Messenger behind a framework-owned `MessageBusInterface`, resolves handlers through `Altair\Container`, and parses transport DSNs from `MESSENGER_*` env vars via `MessengerConfiguration`. Handlers are discovered by scanning for `#[AsHandler(MessageClass::class)]` — no manual registration.

Adding a `queue:` block to a spec extends the scaffolder to also emit a readonly message DTO, a handler stub decorated with `#[AsHandler]`, and a PHPUnit test alongside the HTTP artifacts.

```bash
bin/altair worker                          # consume every configured transport
bin/altair worker --transports=default,high
bin/altair worker --time-limit=3600 --memory-limit=128M
bin/altair worker --limit=100              # exit after N messages
bin/altair worker:show-failed              # list failed envelopes
bin/altair worker:retry-failed             # re-dispatch from failure transport
```

When adding a queue dispatch, write the spec's `queue:` block first and re-scaffold — don't hand-write the DTO + handler + test trio. Keep `Symfony\Component\Messenger\*` imports out of HTTP/domain code; type against `Altair\Messaging\Contracts\MessageBusInterface` instead.

### Event log (session memory)

The `univeros/events` sub-package owns the append-only mutation event log at `.altair/events.jsonl`. Every mutating framework operation (scaffold, migration, rewind, replay, cs:fix, rector, worker consume, manifest generate, eval-with-writes) should call `RecorderInterface::record(Event::create(...))` so the agent has a chronological "what just changed?" record across sessions. Recording is best-effort — the Recorder swallows storage failures, so commands stay correct even when the log can't be written.

`EventsConfiguration` wires `RecorderInterface`, `Reader`, `JsonlStorage`, `SnapshotStorage`, `CheckpointStorage`, and `Scrubber` from `ALTAIR_EVENTS_*` env vars. The Scrubber redacts a default list of secret flags (`--password`, `--token`, `--api-key`, ...) before persistence; add more via `ALTAIR_EVENTS_EXTRA_SECRET_FLAGS`.

```bash
bin/altair events:tail -n 50                      # newest 50 events (human or --format=json)
bin/altair events:show <id>                       # full detail + snapshot if attached
bin/altair events:since <ulid|timestamp>          # everything after a point
bin/altair events:since-last-success              # what happened since the last OK event
bin/altair events:filter --kind=scaffold,migration --status=fail
bin/altair events:stats                           # counts by kind/status + total duration
bin/altair events:checkpoint:create feat/posts    # bookmark current head
bin/altair events:checkpoint:diff feat/posts      # events since the bookmark
bin/altair events:compact --before=2026-04-01     # archive old events to .altair/events.archive/
```

When adding a new mutating command anywhere in the framework, type-hint `RecorderInterface` in the constructor and call `record(Event::create(...))` after success/failure — don't hand-write the event JSON. Use the named-constructor `Event::create()` so the ULID + timestamp stamping stays consistent. Keep `.altair/` in the host app's `.gitignore` (events are local).

### Spec journal (rewind / replay)

The `univeros/scaffold` sub-package now ships a **journal** sub-feature. Every successful `bin/altair spec:scaffold` writes a self-contained `.altair/journal/<id>.json` entry capturing the spec content, per-file SHAs, and full `content_before` for modified files. Failed iterations become recoverable rather than catastrophic.

`ScaffoldCommand` resolves `Altair\Scaffold\Journal\Journal` and `Altair\Events\Contracts\RecorderInterface` as optional constructor dependencies — both nullable so minimal hosts can scaffold without either. When both are bound (via `ScaffoldJournalConfiguration` + `EventsConfiguration`), each scaffold writes a journal entry **and** emits a `scaffold` event into `.altair/events.jsonl`.

```bash
bin/altair journal:list -n 50                  # newest 50 entries (human or --format=json)
bin/altair journal:show <id>                   # full detail (resolves unambiguous prefixes)
bin/altair journal:diff <id>                   # per-file diffs embedded in the entry
bin/altair journal:rewind                      # undo the most recent scaffold
bin/altair journal:rewind --to=<id>            # undo back to a point
bin/altair journal:rewind --dry-run            # preview without writing
bin/altair journal:rewind --force              # clobber hand-edited files (warned about by default)
bin/altair journal:replay <id>                 # re-apply one entry
bin/altair journal:replay --from=<id>          # re-apply forward from a point
bin/altair journal:replay --all                # nuclear — replay the whole journal (confirms)
```

When adding a new spec scaffolder downstream, do NOT bypass `ScaffoldCommand` — write a YAML spec and call `spec:scaffold`. That way the journal entry gets written automatically and `journal:rewind` keeps working. Commands named `journal:*` (not `spec:*`) to avoid collision with introspection's `spec:list` / `spec:show`.

### Test reporter (AI-native PHPUnit JSON)

The `univeros/test-reporter` sub-package ships `Altair\TestReporter\AltairExtension`, a PHPUnit 12 Extension that emits a structured JSON report at the end of every run — failures mapped back to the production source under test, structured diffs for `assertSame` / `assertEquals`, one-word `result` field for agents to branch on.

```xml
<extensions>
    <bootstrap class="Altair\TestReporter\AltairExtension">
        <parameter name="output" value="json"/>
        <parameter name="file" value="build/test-results.json"/>
    </bootstrap>
</extensions>
```

The report's `failures[].source_under_test` field resolves the production file/method via (1) `#[CoversClass]` attributes, (2) legacy `@covers` annotations, (3) namespace heuristic (`Altair\Tests\Http\Support\HttpCacheTest` → `Altair\Http\Support\HttpCache`). When no signal matches, the field is `[]` and the agent knows it's on its own.

When writing new tests, **don't fight the resolver** — name the test class `<Class>Test` in a sibling `Tests\` namespace, and name test methods with a prefix matching the source method (`testIsCacheableReturnsTrueWithMaxAge` covers `isCacheable`). When that's awkward, prefer `#[CoversClass(X::class)]` as an explicit override; do NOT use `@covers` annotations in new code (legacy fallback only).

Output is deterministic for the same outcomes — golden-file-safe for CI. `build/test-results.json` is gitignored.

### Plan/Skill choices for new work

The PHP 7.2 → 8.3 modernization is finished (see [AGENT.md §7](AGENT.md)); there is no phase backlog. For new work:

- **New feature spanning several files:** `architect` then `planner`, then TDD.
- **New HTTP endpoint:** write the YAML spec and `bin/altair spec:scaffold` — don't hand-write the Action/Input/Responder triple.
- **Static analysis:** PHPStan is at **level 8 with no baseline**. Keep it there — fix at root cause; only add an inline `ignoreErrors` entry in `phpstan.neon.dist` with a `// reason:` comment. Never regenerate a `phpstan-baseline.neon`.
- **Before pushing:** run `composer rector` (whole-tree dry-run) — it's a CI gate but not part of `composer qa`.

---

## 4. Conventions Claude must follow

These are stricter than what other agents need because of past patterns in this codebase:

- **Immutability — required.** Never mutate value objects. New copies via `withFoo()` methods. See `Altair\Cookie\Cookie` as the reference implementation.
- **Many small files > few big ones.** 200-400 LOC typical, 800 hard cap. Extract aggressively.
- **`declare(strict_types=1)` is non-negotiable** — every file, every time. Currently every source file under `src/` complies; don't be the one who breaks it.
- **Native types beat PHPDoc.** Add PHPDoc only for `array<K, V>` shapes or unions PHP can't express natively. Don't write `@param string $foo` next to `string $foo` — Rector deletes those anyway.
- **No emojis** in source files, commit messages, or docs unless the user explicitly asks for them.
- **No new code without tests.** TDD per [AGENT.md §6](AGENT.md#6-testing). 80%+ coverage on new code.
- **Don't reintroduce abandoned deps** (Zend\Diactoros, relay/middleware, Flysystem v1 adapters — see [AGENT.md §5](AGENT.md#what-not-to-do)).

---

## 5. Things to flag to the user

When you encounter these, **stop and surface them** instead of working around:

1. **Composer install fails:** a version conflict. Don't relax constraints — diagnose with `composer why-not <pkg> <version>` and report.
2. **A file uses `Zend\Diactoros\*`** (or any abandoned dep from [AGENT.md §4](AGENT.md#4-php--dependency-baseline)): that's a regression — the migration removed these. Fix the import to `Laminas\Diactoros\*` and report which file.
3. **A test uses `Relay\RelayBuilder` or double-pass `$next($req, $res)`:** a PSR-15 regression — middleware is single-pass now. Flag it.
4. **PHPStan finds an issue you'd ignore:** add to `ignoreErrors` only with an inline `// reason: …` comment.
5. **A change requires editing 10+ files mechanically:** stop, suggest Rector or a one-off transform script instead.

---

## 6. Git / PR workflow

- **Branch:** work on `master` directly for this project (no PR workflow established yet — confirm with user before opening one).
- **Commits:** style is `re #<issue> <subject>` (see `git log`). Keep that style for issue-linked work; conventional commits (`feat:`, `fix:`) for ad-hoc work.
- **Never auto-push.** Always confirm before `git push`.
- **Never `--no-verify`.** If pre-commit (CS-Fixer) fails, fix the underlying lint issue.
- **CHANGELOG.md ([Keep a Changelog](https://keepachangelog.com)).** Every user-facing change adds a bullet under `## [Unreleased]` (grouped Added / Changed / Fixed / Removed / Deprecated / Security) referencing its PR/issue, e.g. `(#231)`. On release: rename `[Unreleased]` to `[vX.Y.Z] - YYYY-MM-DD`, add a fresh empty `[Unreleased]`, update the compare links at the bottom of the file, and reuse that version's section as the annotated-tag body. Release notes are no longer tag-only — `CHANGELOG.md` is the source of truth, and `[Unreleased]` always shows what's merged-but-unpublished.

---

## 7. State at last update (2026-06)

- The PHP 7.2 → 8.3 modernization is **complete** (Phases 1–4). PHPStan runs at **level 8 with no baseline**; the 8.3 + 8.4 CI matrix is green. Don't reintroduce a `phpstan-baseline.neon`.
- `composer.lock` is gitignored and will be regenerated by the user. Run `git status` first regardless.
- Open work is tracked in GitHub issues, not in AGENT.md §7 (which is now a historical record of the finished migration).

When in doubt, **read [AGENT.md](AGENT.md) first**, then ask the user before making architectural choices.
