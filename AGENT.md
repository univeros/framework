# AGENT.md

Canonical, vendor-neutral guide for AI coding agents (Claude Code, Cursor, Aider, Copilot, etc.) working on the **Univeros / Altair Framework**.

This file is the source of truth. Tool-specific entry points (`CLAUDE.md`) point here.

---

## 1. Project at a glance

- **Name:** `univeros/framework`, PHP framework, MIT licensed.
- **Root namespace:** `Altair\*` (legacy reasons; the Composer package is `univeros/*` and 40 sub-packages are bundled via `replace`).
- **Origin:** Started ~7 years ago as a learning vehicle. Originally targeted PHP 7.0-7.2 and PSR-3/6/7/11/15/16 v1.
- **Current target:** PHP **8.3+** (modernization started 2026-05).
- **Architecture style:** Library-first / framework-agnostic; every sub-package is meant to be usable standalone behind PSR interfaces.

---

## 2. Repository layout

```
.
├── .github/workflows/ci.yml      ← CI (PHP 8.3 + 8.4 matrix, PHPStan, CS-Fixer, Rector, Codecov)
├── .php-cs-fixer.dist.php        ← PHP-CS-Fixer v3 config (@PER-CS2.0, @PHP83Migration)
├── phpunit.xml.dist              ← PHPUnit 12 config
├── phpstan.neon.dist             ← PHPStan config (level 8, no baseline)
├── rector.php                    ← Rector config (PHP 8.3 + code-quality + dead-code sets)
├── composer.json                 ← Root manifest (monorepo via `replace`)
├── src/Altair/
│   ├── AgentSpec/     ← AI-readable manifests describing every package + the host app (.agent/, manifest:generate)
│   ├── Bootstrap/     ← bin/altair new — materialises a runnable API from the skeleton template
│   ├── Cache/         ← PSR-6 + PSR-16 cache; filesystem/Redis/Predis/Memcached backends
│   ├── Cli/           ← Attribute-driven CLI on top of Symfony Console
│   ├── Common/        ← Pure-PHP utilities (string/array helpers, key-value registry) shared everywhere
│   ├── Configuration/ ← Container-aware config; loads .env (phpdotenv 5) + wires bindings
│   ├── Container/     ← Reflection-backed PSR-11 DI: auto-wiring, contextual bindings, tags, decorators, scopes
│   ├── Cookie/        ← Immutable HTTP cookie value objects + PSR-7 read/write manager
│   ├── Courier/       ← Synchronous command bus routing immutable messages to handlers via middleware
│   ├── Data/          ← Immutable-by-default data objects: JSON/Serializable, Carbon date mutators
│   ├── Doctor/        ← bin/altair doctor — health checks; agent-actionable JSON + human text
│   ├── Eval/          ← bin/altair eval — sandboxed PHP-snippet runner inside the project container
│   ├── Events/        ← Append-only mutation event log (.altair/events.jsonl) — session memory; events:*
│   ├── Examples/      ← Curated idiomatic-pattern library + CLI/MCP discovery tools
│   ├── Filesystem/    ← Flysystem v3 wrapper: local/S3/SFTP/FTP/Dropbox behind one API
│   ├── Happen/        ← PSR-14 event dispatcher: priorities, subscribers, wildcards, batch dispatch
│   ├── Http/          ← PSR-15 stack: Action/Domain/Input/Responder, FastRoute, content negotiation, JWT
│   ├── Idempotency/   ← Stripe-style Idempotency-Key primitive: storage contract + adapters
│   ├── Index/         ← bin/altair index — AST+spec symbol index (find-usages, callers, dead-code); SQLite, JSON
│   ├── Introspection/ ← "What's wired right now?" inspectors: container/routes/listeners/middleware/specs/config
│   ├── Logging/       ← PSR-3 logging backed by Monolog, wired from LOG_*; JSON-lines to stderr by default
│   ├── Mcp/           ← Model Context Protocol server exposing the framework as MCP tools for agents
│   ├── Messaging/     ← MessageBus + worker bridge over Symfony Messenger; scaffold queue: block
│   ├── Middleware/    ← Generic typed-Payload pipeline driven by a Runner (NOT PSR-15 HTTP middleware)
│   ├── MigrationIntelligence/ ← bin/altair db:migration-plan — safe Cycle migration plans from spec/entity diffs
│   ├── Module/        ← Pluggable extensions: one class self-registers routes/entities/migrations (module:new)
│   ├── Observability/ ← observability:* — native OTel-compatible spans/metrics (OTLP-JSON) + per-request middleware
│   ├── Observatory/   ← Dev-only web monitoring panel over introspection/doctor/events/messaging/persistence
│   ├── Persistence/   ← Repository + UnitOfWork over Cycle ORM v2 + migration CLI (db:migrate*)
│   ├── Profiling/     ← bin/altair profile — sampling profiler (excimer/xdebug): call tree, flamegraph, diff
│   ├── Sanitation/    ← Composable input sanitation: untrusted input → safe canonical form
│   ├── Scaffold/      ← YAML-spec-to-code: Action/Input/Responder + OpenAPI + tests; journal, SDK emitters, openapi:import
│   ├── Security/      ← Crypto primitives: key derivation (HKDF/PBKDF2), symmetric encryption, timing-safe MAC
│   ├── Session/       ← Server-side sessions: file, MongoDB, PDO (MySQL/Postgres/SQLite), Redis handlers
│   ├── Structure/     ← Typed structures (Map, Set, Vector, Deque, Queue, Stack, PriorityQueue) in pure PHP
│   ├── Suggest/       ← bin/altair suggest — proposes refactors (dead bindings, fat ctors, routes w/o specs)
│   ├── TestReporter/  ← AI-native PHPUnit reporter: JSON failures mapped to source-under-test
│   ├── Tinker/        ← bin/altair tinker — PsySH REPL with the container in scope (dev tool, not an agent surface)
│   ├── Validation/    ← Rule-based input validation — the gatekeeping counterpart to Sanitation
│   └── Webhooks/      ← Signing, inbound-verify middleware, outbound dispatcher (retry/dead-letter/replay)
└── tests/             ← Mirrors `src/Altair` layout. Suffix `Test.php`. Fixtures: `tests/{pkg}/fixtures.php`.
```

Each sub-package has its **own `composer.json`** (so split repos remain valid). The root `composer.json` declares them in the `replace` section so they're satisfied by this monorepo when consumed together.

---

## 3. Common commands

> All commands run from the repository root. Composer scripts wrap the binaries; prefer them.

```bash
composer install                  # install deps
composer update -W                # bump deps within constraints

composer test                     # vendor/bin/phpunit
composer test:coverage            # phpunit + HTML coverage in build/coverage

composer cs                       # PHP-CS-Fixer dry-run with diff
composer cs:fix                   # apply fixer

composer stan                     # PHPStan
composer rector                   # Rector dry-run
composer rector:fix               # apply Rector

composer qa                       # cs + stan + test (the pre-merge gate)
```

CI runs the same scripts on every push/PR (see `.github/workflows/ci.yml`).

---

## 4. PHP & dependency baseline

**PHP:** `>=8.3`. We use 8.3 features (typed class constants, `json_validate`, `#[\Override]`, readonly classes, enums, first-class callables, `match`, nullsafe, intersection/DNF types).

**Core runtime deps (current majors):**

| Package | Version | Used by / notes |
|---|---|---|
| `psr/log` ^3 · `psr/container` ^2 · `psr/cache` ^3 · `psr/simple-cache` ^3 · `psr/clock` ^1 · `psr/event-dispatcher` ^1 | | PSR interfaces the framework implements |
| `psr/http-message` ^2 · `psr/http-factory` ^1.1 · `psr/http-server-middleware` / `psr/http-server-handler` ^1 | | PSR-7 + PSR-15 (single-pass) |
| `laminas/laminas-diactoros` | ^3.5 | PSR-7 implementation (replaced abandoned `zend-diactoros`) |
| `relay/relay` | ^2.1 | PSR-15 single-pass dispatcher |
| `nikic/fast-route` | ^1.3 | HTTP routing |
| `neomerx/cors-psr7` ^3 · `willdurand/negotiation` ^3.1 | | CORS + content negotiation |
| `lcobucci/jwt` | ^5.3 | HTTP JWT authentication |
| `monolog/monolog` | ^3 | backs `univeros/logging` (PSR-3) |
| `symfony/console` | ^7 | the `bin/altair` CLI (`univeros/cli`) |
| `symfony/messenger` | ^7 | `univeros/messaging` bus + worker |
| `symfony/serializer` · `symfony/uid` · `symfony/yaml` | ^7 | serialization · ULIDs (events/journal) · spec parsing |
| `cycle/orm` + `cycle/database` `cycle/migrations` `cycle/annotated` `cycle/schema-builder` | ^2 / ^4 | `univeros/persistence` ORM bridge + migrations |
| `nikic/php-parser` ^5 · `spiral/tokenizer` ^3.13 | | AST parsing for the scaffold drift linter + `univeros/index` |
| `opis/json-schema` | ^2.4 | OpenAPI / spec schema validation |
| `vlucas/phpdotenv` | ^5.6 | `.env` loading |
| `nesbot/carbon` | ^3.8 | date mutators in `univeros/data` |
| `league/flysystem` | ^3.29 | `univeros/filesystem` (v3) |

**Dev tooling:**

| Tool | Version | Notes |
|---|---|---|
| `phpunit/phpunit` | ^11.4 | |
| `phpstan/phpstan` | ^2.1 | level 8, **no baseline** |
| `rector/rector` | ^2.0 | dry-run in CI's `static-analysis` job, not in `composer qa` |
| `friendsofphp/php-cs-fixer` | ^3.64 | `@PER-CS2.0` + `@PHP83Migration` |
| `squizlabs/php_codesniffer` | ^3.10 | |
| `psy/psysh` | ^0.12 | powers `bin/altair tinker` |
| `roave/security-advisories` | dev-latest | |

Optional test deps gated behind extensions/services (skipped when absent): `mongodb/mongodb`, `predis/predis`, `pda/pheanstalk`, the `league/flysystem-*` adapters, and `ext-{mongodb,redis,memcached,intl,openssl}`.

---

## 5. Coding conventions

> Conventions below are required for any new or modified file.

### File header

Every PHP file starts with:

```php
<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\<Subpackage>;
```

`declare(strict_types=1)` is mandatory; 100% of source files already comply.

### Style

- **PER-CS 2.0** (PHP-CS-Fixer `@PER-CS2.0` + `@PHP83Migration`).
- 4-space indent, LF line endings, no trailing whitespace (enforced by `.editorconfig`).
- Short array syntax `[]`. Imports ordered. No useless `else`/`return`. Ordered class elements.
- Use **native types** for parameters/return/properties. Use PHPDoc **only** for collections (`array<int, Foo>`) or unions PHP can't express natively.

### Immutability

New objects, never mutate. Value objects use `readonly` properties (or `readonly class`). Methods that "change" a value return a new instance (`withFoo()`). See `Altair\Cookie\Cookie` for the canonical pattern.

### Files & functions

- **Files:** 200-400 LOC typical, 800 hard cap.
- **Functions:** under 50 LOC. Extract helpers freely.
- **Nesting:** max 4 levels. Use early returns / guard clauses.
- Organize by feature/sub-package, not by type.

### Error handling

- Throw typed exceptions from `Altair\<Subpackage>\Exception\*`.
- Never silently swallow errors. Log on the server side with full context.
- Validate at boundaries (HTTP request, env vars, file contents). Trust internal calls.

### Naming

- Interfaces live in `Contracts/` and end in `Interface` (`CacheItemStorageInterface`). Yes, this is unusual; keep it consistent with existing code.
- Traits live in `Traits/` and end in `Trait`.
- Exceptions live in `Exception/`.
- Configuration classes live in `Configuration/` and implement `ConfigurationInterface`.
- Factories live in `Factory/` and end in `Factory`.

### What NOT to do

- Don't add backwards-compat shims for PHP < 8.3.
- Don't reintroduce `Zend\Diactoros\*`; use `Laminas\Diactoros\*`.
- Don't use `relay/middleware` or double-pass middleware; Relay 2 is PSR-15 single-pass.
- Don't add Flysystem v1 adapters (Rackspace, GridFs, ZipArchive, WebDAV, cached-adapter; **removed in v3**).
- Don't write `@var`/`@param`/`@return` when a native type works. PHPDoc supplements types, doesn't replace them.

### Determinism (every emitter & command): #74

Determinism is a first-class quality, not a nice-to-have. Any command or emitter that writes files or produces `--format=json` MUST be **byte-stable**: the same inputs produce byte-identical output across runs, machines, and PHP minor versions. Agents read `diff` as signal; non-deterministic output looks like drift and triggers phantom "fixes".

The standard: a deterministic emitter has:

1. **Byte-identical output:** same content, same LF line endings, same trailing newline.
2. **Stable ordering:** sort by a stable key (name, numeric id) before emitting anywhere you iterate a map/set, and `->sortByName()` on every `Finder`/`scandir` used for code generation (FS order is inode-dependent).
3. **No wall-clock timestamps inside emitted content,** except a single explicit `generated_at` / `duration_ms` field where one is genuinely useful.
4. **No machine identifiers:** no hostname, username, or absolute paths.
5. **No nondeterministic randomness:** derive any UUID from the spec SHA (`uuid5`), never `random_*`.

Enforcement is layered and live:

- **In-process tests:** `tests/Determinism/TwiceHarness.php` is the shared harness, and `tests/AgentSpec/Determinism/`, `tests/Scaffold/Determinism/` ship "run twice, diff empty" tests for the manifest pipeline, the OpenAPI fragment merger, and the TypeScript + Python SDK emitters. Add one anywhere you add a new emitter.
- **CI determinism gate:** `.github/workflows/ci.yml` regenerates `.agent/` and `git diff --exit-code`s it on every PR. A non-deterministic emitter fails the gate.
- **`bin/altair doctor`:** the `determinism_check` from `univeros/doctor` runs the same regenerate-and-diff gate against any generators a host configures (see `DoctorConfiguration`'s constructor).
- **Skeleton workflow:** `src/Altair/Bootstrap/resources/skeleton/.github/workflows/determinism.yml` ships with `bin/altair new`, so generated host projects inherit the gate.

**Any new package added to the framework must meet this standard.** Tracked in [#74](https://github.com/univeros/framework/issues/74).

---

## 6. Testing

- Framework: **PHPUnit 12**, attribute-style (`#[DataProvider]`, `#[Group]`, `#[Test]`).
- Bootstrap: `tests/bootstrap.php`. Per-package fixtures in `tests/{Container,Courier,Sanitation,Validation}/fixtures.php`.
- Suffix `Test.php`, mirror the `src/Altair/<Pkg>` layout under `tests/<Pkg>/`.
- Coverage target: **80%+** for new code (unit + integration).

```bash
vendor/bin/phpunit                              # full suite (canonical gate)
vendor/bin/phpunit --filter CookieManagerTest   # one test class
composer test:par                               # parallel run via paratest — fast local loop
```

### Running the integration suite

Some packages talk to a real service (Redis, MongoDB, Memcached, Beanstalk, a SQL database). Those tests resolve their endpoint in this order, and **skip gracefully** when none is available, so `composer test` stays green and Docker-free on a bare machine:

1. an explicit env endpoint (e.g. `REDIS_HOST` / `REDIS_PORT`);
2. a service already listening on the conventional local port (a CI service container, or a locally-running server), reused as-is;
3. a throwaway container booted via the `docker` CLI ([tests/Support/Integration/DockerContainer.php](tests/Support/Integration/DockerContainer.php)), torn down on shutdown;
4. otherwise the test is skipped.

So with Docker running you get the integration coverage locally with **no global service install and no PHP client extension,** provided the client is pure-PHP (`predis/predis`, `pda/pheanstalk`, PDO). Tests using `ext-redis` / `ext-mongodb` / `ext-memcached` still need that extension loaded in the CLI runtime even with a container, so they skip without it. `PredisCacheItemStorageTest` is the reference port; resolve new ones through a small per-service helper like [`RedisServer`](tests/Support/Integration/RedisServer.php).

> We deliberately do **not** use testcontainers-php: its Docker client requires `psr/http-message ^2`, which conflicts with this tree's `relay/relay ~1.0` and `neomerx/cors-psr7 ^1.0`. The dependency-free `docker`-CLI helper does the same job.

### Parallel runs (`composer test:par`)

`paratest` runs the suite across CPU cores for a fast local loop. **`composer test` (single-process) remains the canonical gate** (CI uses it): a few suites that share an on-disk fixture path (`tests/Cache/tmp` for the filesystem cache, the events log under `Events\ReaderTest`) are not yet isolated per worker, so they can collide under `test:par`. Making every service/file-backed test parallel-safe (per-worker container / per-test keyspace, DB name, or temp dir keyed off `TEST_TOKEN`) is tracked in [#129](https://github.com/univeros/framework/issues/129).

---

## 7. Modernization status (started 2026-05)

The migration from PHP 7.2 / abandoned deps to PHP 8.3 is **complete.** All phases (1, 2, 3a–3d, 4) are done. The tree targets PHP 8.3+, runs a PHP 8.3 + 8.4 CI matrix, and passes **PHPStan level 8 with no baseline**. The section below is kept as a record of what each phase changed; there is no outstanding modernization work. New work is tracked in the open issues, not here.

### Phase 1: COMPLETE

- Composer manifest rewritten (root + 16 sub-packages), `,,` syntax error fixed.
- Dep majors bumped (see §4). PHP floor `>=8.3`.
- `Zend\Diactoros\*` → `Laminas\Diactoros\*` swapped in 8 files.
- `.travis.yml` → `.github/workflows/ci.yml` (8.3 + 8.4 matrix).
- `.php_cs` → `.php-cs-fixer.dist.php` (v3 format, PER-CS 2.0).
- `phpunit.xml.dist` → PHPUnit 12 schema.
- `phpstan.neon.dist` + `rector.php` added.
- `.pre-commit-config.yaml` updated.
- `.gitignore` covers new tool caches.

### Phase 2: COMPLETE (Rector automated)

`composer rector:fix` has been applied across the codebase; `rector process --dry-run` is **green on master** and runs in CI's `static-analysis` job (note: it is **not** part of `composer qa`, so run it manually before pushing). All the configured sets are satisfied: constructor promotion, native types from PHPDoc, `match`, nullsafe/`??=`/first-class-callables/`str_contains`, dead-code/early-return/`instanceof` simplifications, and `PRIVATIZATION`.

PHP 8.4's `ExplicitNullableParamTypeRector` was added (target lifted to 8.4 to arm it) so implicit-nullable params (`Type $x = null`) stay fixed going forward; see [#93](https://github.com/univeros/framework/issues/93).

One rule is **deliberately skipped** in `rector.php`: `NewInInitializerRector`. Collapsing `?? new X()` bodies into promoted `= new X()` defaults narrows public constructor contracts (an explicit `null` arg becomes a TypeError); declined as a needless BC change. Remove the skip to adopt it intentionally.

### Phase 3a: COMPLETE (HTTP middleware to PSR-15)

All `Altair\Http\Middleware\*` classes migrated:

- `Altair\Http\Contracts\MiddlewareInterface` now `extends Psr\Http\Server\MiddlewareInterface`; keeps the `ATTRIBUTE_*` typed class constants (PHP 8.3).
- All 14 middleware implement `process(ServerRequestInterface, RequestHandlerInterface): ResponseInterface`. Short-circuiting middleware receive `ResponseFactoryInterface` via constructor injection.
- `relay/middleware` v1 adapters (`AbstractContentHandlerMiddleware`, `FormContentMiddleware`, `JsonContentMiddleware`) are reimplemented inline; the package is gone in Relay 2.
- `Altair\Http\Resolver\ContainerResolver` no longer implements the removed `Relay\ResolverInterface`; it's a plain `__invoke(object|string): object` callable, which is what Relay 2 accepts.
- `Altair\Http\Configuration\RelayConfiguration` uses `new Relay($queue->toArray(), $resolver)` (Relay 2 ctor).
- `tests/Http/Middleware/AbstractMiddlewareTest::dispatch()` builds a PSR-15 pipeline using `Relay\Relay::handle()` with an anonymous terminal handler.
- `Altair\Http\Responder\{Compound,Formatted}Responder` typehint the resolver as `callable` instead of `Relay\ResolverInterface`.
- **Bug fix as part of migration:** `CsrfMiddleware` previously returned 403 on *valid* tokens (`if ($isPost && validate)`, inverted). Corrected to `if ($unsafeMethod && !validate)`.

Decorator middleware that depend on the next response (CORS, cache headers) must now run **before** terminal middleware (ActionMiddleware) in the queue so they wrap the response that bubbles back up.

### Phase 3b: COMPLETE (Dotenv v2 → v5)

- `Altair\Configuration\EnvironmentConfiguration` now uses `Dotenv\Dotenv::createImmutable($dir, $file)` (or `createMutable` when constructed with `$immutable = false`). The `Dotenv\Loader` class is internal in v5 and no longer used directly.
- `tests/Configuration/EnvironmentConfigurationTest` migrated from PHPUnit 7 annotation-based exception expectations (`@expectedException`, `@expectedExceptionMessageRegExp`) to method-based (`expectException()`, `expectExceptionMessageMatches()`).

### Phase 3c: COMPLETE (Flysystem v1 → v3)

- **Deleted** configurations for adapters removed in Flysystem v3: Rackspace, Azure, WebDAV, ZipArchive, GridFs.
- **Rewrote** the survivors against v3 adapter constructors: `LocalFilesystemAdapter`, `FtpAdapter` (uses `FtpConnectionOptions::fromArray`), `AwsS3V3Adapter`, `SftpAdapter` (uses `SftpConnectionProvider`), Spatie `DropboxAdapter`.
- `Altair\Filesystem\Contracts\FilesystemAdapterInterface` now extends `League\Flysystem\FilesystemOperator` (was `FilesystemInterface` in v1).
- `Altair\Filesystem\Adapter\FlysystemAdapter` rewritten as a thin explicit decorator (no more magic `__call`) wrapping a `FilesystemOperator`. All v3 methods are forwarded; `exists/prepend/append/listDirectories` rewritten against v3's StorageAttributes/DirectoryListing iterables.
- `FilesystemAdapterConfiguration` no longer wires a `CachedAdapter`; Flysystem v3 removed caching from core. Wrap with a caching decorator separately if needed.
- The `FlysystemAdapter.php` exclusion from PHPUnit coverage, PHPStan, and Rector is removed; the new implementation is testable.

### Phase 3d: COMPLETE (targeted modern idioms)

- Value objects → `readonly`: **done**.
- Sentinel `class const` → **enums**: **done**.
- Rector-driven cleanup of PHPDoc-only types: **done** (`TYPE_DECLARATION` set applied; `rector --dry-run` clean).
- `Altair\Happen\*` PSR-14: **done** ([#97](https://github.com/univeros/framework/issues/97)): the dispatcher/provider now implement PSR-14's object-based interfaces alongside the original name-based API.

### Phase 4: COMPLETE (static analysis + test attribute migration)

1. **PHPStan level 5 → 8: done** ([#96](https://github.com/univeros/framework/issues/96)). The tree analyses at **level 8 with no `phpstan-baseline.neon`**; the original 746-error baseline was fully burned down. The only remaining suppressions are a handful of inline `ignoreErrors` in `phpstan.neon.dist`, each with a comment explaining why (optional ext-* stubs, intentional collection-trait variance, etc.). Keep it that way: fix at root cause, and never reintroduce a baseline.
2. PHPUnit annotation → attribute migration: **done**. The suite uses `#[DataProvider]` / `#[CoversClass]`; the only remaining `@covers` is `tests/TestReporter/Fixtures/LegacyCoversAnnotationTest.php`, an intentional fixture exercising the test-reporter's legacy-annotation fallback.
3. Per-sub-package coverage ≥ 80% remains a standing quality goal (not a migration blocker).

---

## 8. Working in this repo

### Where to start

- **Adding a feature inside an existing sub-package:** read that package's `Contracts/`, then its concrete classes. Configurations live in `Configuration/`.
- **Adding a new sub-package:** mirror the layout of `src/Altair/Structure/` (smallest, cleanest). Add a `composer.json`, register in root `replace`, add an autoload entry.
- **Touching HTTP/middleware:** PSR-15 single-pass only. Implement `Psr\Http\Server\MiddlewareInterface`.
- **Touching cache:** PSR-6 (`CacheItemPoolInterface`) is the primary contract; PSR-16 is a façade over it.
- **Adding HTTP endpoints:** write a YAML spec under `api/` and run `bin/altair spec:scaffold api/<file>.yaml`. The generator emits Action / Input / Responder / domain stub / test / OpenAPI fragment / route entry. Hand-edits to these files surface via `bin/altair spec:lint`.

### Verification before claiming done

Run locally (or in CI):

```bash
composer qa   # cs + stan + test
```

If Rector or PHPStan find new issues your change introduced, fix at root cause; don't add `ignoreErrors` entries without justification in a comment.

### What's risky in this codebase

- **Env-/service-backed tests only run where the extension or service exists.** Tests needing `ext-mongodb`, `ext-redis`, `ext-memcached`, `ext-apcu`, `ext-excimer`, or a live database skip (or error in `setUp`) on a machine without them; the SDK compile tests need `tsc` / `mypy`. A green run with those skipped is expected; don't read the skips as failures. Parallel + container-backed infra to make them runnable everywhere is tracked in [#129](https://github.com/univeros/framework/issues/129).
- **Rector is a CI gate but is NOT in `composer qa`.** CI's `static-analysis` job runs `rector process --dry-run` over the whole tree (PHP 8.3); `composer qa` is only cs + stan + test. Run `composer rector` manually before pushing or CI will catch drift you didn't see locally.
- **`composer.lock` is gitignored.** `composer install` regenerates it; don't commit one.

---

## 9. References

- PSR specs: <https://www.php-fig.org/psr/>
- PHP 8.3 release notes: <https://www.php.net/releases/8.3/en.php>
- Rector docs: <https://getrector.com/>
- PHPStan rule levels: <https://phpstan.org/user-guide/rule-levels>
- PHPUnit 12 attributes: <https://docs.phpunit.de/en/12.5/attributes.html>
- Flysystem v3 migration: <https://flysystem.thephpleague.com/docs/upgrade-from-1.x/>
- Laminas Diactoros (PSR-7): <https://docs.laminas.dev/laminas-diactoros/>
- Relay 2 (PSR-15): <https://github.com/relayphp/Relay.Relay>
- Dotenv 5: <https://github.com/vlucas/phpdotenv#usage>
