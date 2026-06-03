# AGENT.md

Canonical, vendor-neutral guide for AI coding agents (Claude Code, Cursor, Aider, Copilot, etc.) working on the **Univeros / Altair Framework**.

This file is the source of truth. Tool-specific entry points (`CLAUDE.md`) point here.

---

## 1. Project at a glance

- **Name:** `univeros/framework` — PHP framework, MIT licensed.
- **Root namespace:** `Altair\*` (legacy reasons; the Composer package is `univeros/*` and 18 sub-packages are bundled via `replace`).
- **Origin:** Started ~7 years ago as a learning vehicle. Originally targeted PHP 7.0-7.2 and PSR-3/6/7/11/15/16 v1.
- **Current target:** PHP **8.3+** (modernization started 2026-05).
- **Architecture style:** Library-first / framework-agnostic — every sub-package is meant to be usable standalone behind PSR interfaces.

---

## 2. Repository layout

```
.
├── .github/workflows/ci.yml      ← CI (PHP 8.3 + 8.4 matrix, PHPStan, CS-Fixer, Rector, Codecov)
├── .php-cs-fixer.dist.php        ← PHP-CS-Fixer v3 config (@PER-CS2.0, @PHP83Migration)
├── phpunit.xml.dist              ← PHPUnit 11 config
├── phpstan.neon.dist             ← PHPStan config (start at level 5, raise gradually)
├── rector.php                    ← Rector config (PHP 8.3 + code-quality + dead-code sets)
├── composer.json                 ← Root manifest (monorepo via `replace`)
├── src/Altair/
│   ├── Cache/         ← PSR-6 + PSR-16 cache; storage adapters for filesystem/Memcached/Redis/Predis
│   ├── Common/        ← Cross-cutting helpers (intl, primitives)
│   ├── Configuration/ ← Dotenv-based config + container bindings
│   ├── Container/     ← DI container (PSR-11)
│   ├── Cookie/        ← Cookie value objects (PSR-7 aware)
│   ├── Courier/       ← Mail/transport abstraction
│   ├── Data/          ← Entity/DTO base + attribute mutators
│   ├── Events/        ← Append-only mutation event log (.altair/events.jsonl) + CLI (events:tail/show/since/checkpoint/compact)
│   ├── Filesystem/    ← Flysystem v3 adapters & configuration
│   ├── Happen/        ← Event dispatcher
│   ├── Http/          ← PSR-7/15 stack: routing (FastRoute), middleware, CORS, JWT, content negotiation
│   ├── Introspection/ ← "What's wired into this project right now?" CLI inspectors (container:inspect, routes:list, listeners:list, middleware:list, manifest:diff, spec:list/show, config:dump)
│   ├── Messaging/     ← MessageBus + worker over Symfony Messenger, attribute-driven handlers, scaffold queue: block
│   ├── Middleware/    ← PSR-15 middleware primitives
│   ├── Module/        ← Pluggable extension modules: one class self-registers a feature's routes/entities/migrations (bin/altair module:new)
│   ├── Persistence/   ← Repository/UnitOfWork over Cycle ORM v2 + migration CLI
│   ├── Sanitation/    ← Input sanitation rules
│   ├── Scaffold/      ← YAML-spec-to-code generator (bin/altair spec:scaffold), with optional persistence: and queue: blocks; Journal sub-feature (journal:*) for rewindable scaffold ops; SDK emitters (spec:emit-sdk typescript|python) for typed clients
│   ├── Security/      ← Hashing, encryption, CSRF tokens
│   ├── Session/       ← Session handlers (file, Redis, Mongo)
│   ├── Structure/     ← Collection primitives (Map, Set, etc.)
│   ├── TestReporter/  ← AI-native PHPUnit Extension: JSON output with failures mapped to source-under-test (`bin/altair test --format=json`)
│   └── Validation/    ← Validation rules + middleware
└── tests/             ← Mirrors `src/Altair` layout. Suffix `Test.php`. Fixtures: `tests/{pkg}/fixtures.php`.
```

Each sub-package has its **own `composer.json`** (so split repos remain valid). The root `composer.json` declares them in the `replace` section so they're satisfied by this monorepo when consumed together.

---

## 3. Common commands

> All commands run from the repository root. Composer scripts wrap the binaries — prefer them.

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

**Core deps (current majors):**

| Package | Version | Notes |
|---|---|---|
| `nikic/php-parser` | ^5 | Used by `univeros/scaffold` drift linter to re-parse emitted code |
| `symfony/yaml` | ^7 | Spec parsing in `univeros/scaffold` |
| `psr/log` | ^3 | Typed signatures |
| `psr/container` | ^2 | Typed `get`/`has` |
| `psr/cache` | ^3 | |
| `psr/simple-cache` | ^3 | |
| `psr/http-message` | ^2 | PSR-7 with return types |
| `psr/http-server-middleware` / `psr/http-server-handler` | ^1 | PSR-15 (single-pass) |
| `psr/http-factory` | ^1.1 | |
| `laminas/laminas-diactoros` | ^3.5 | Replaces abandoned `zendframework/zend-diactoros` |
| `nikic/fast-route` | ^1.3 | |
| `relay/relay` | ^2.1 | **PSR-15** single-pass dispatcher (v1 used double-pass + `RelayBuilder` — both gone) |
| `vlucas/phpdotenv` | ^5.6 | API changed: `Dotenv::createImmutable($path)->load()` |
| `nesbot/carbon` | ^3.8 | |
| `neomerx/cors-psr7` | ^3 | |
| `willdurand/negotiation` | ^3.1 | |
| `league/flysystem` | ^3.29 | **Major rewrite** — see §7 |
| `micheh/psr7-cache` | ^0.5 | No newer release; review if it falls behind |

**Dev tooling:**

| Tool | Version |
|---|---|
| `phpunit/phpunit` | ^11.4 |
| `phpstan/phpstan` | ^1.12 |
| `rector/rector` | ^1.2 |
| `friendsofphp/php-cs-fixer` | ^3.64 |
| `squizlabs/php_codesniffer` | ^3.10 |
| `roave/security-advisories` | dev-latest (pinned via `prefer-stable`) |

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

`declare(strict_types=1)` is mandatory — 100% of source files already comply.

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

- Interfaces live in `Contracts/` and end in `Interface` (`CacheItemStorageInterface`). Yes, this is unusual — keep it consistent with existing code.
- Traits live in `Traits/` and end in `Trait`.
- Exceptions live in `Exception/`.
- Configuration classes live in `Configuration/` and implement `ConfigurationInterface`.
- Factories live in `Factory/` and end in `Factory`.

### What NOT to do

- Don't add backwards-compat shims for PHP < 8.3.
- Don't reintroduce `Zend\Diactoros\*` — use `Laminas\Diactoros\*`.
- Don't use `relay/middleware` or double-pass middleware — Relay 2 is PSR-15 single-pass.
- Don't add Flysystem v1 adapters (Rackspace, GridFs, ZipArchive, WebDAV, cached-adapter — **removed in v3**).
- Don't write `@var`/`@param`/`@return` when a native type works. PHPDoc supplements types, doesn't replace them.

### Determinism (every emitter & command) — #74

Determinism is a first-class quality, not a nice-to-have. Any command or emitter that writes files or produces `--format=json` MUST be **byte-stable**: the same inputs produce byte-identical output across runs, machines, and PHP minor versions. Agents read `diff` as signal — non-deterministic output looks like drift and triggers phantom "fixes".

The standard — a deterministic emitter has:

1. **Byte-identical output** — same content, same LF line endings, same trailing newline.
2. **Stable ordering** — sort by a stable key (name, numeric id) before emitting anywhere you iterate a map/set, and `->sortByName()` on every `Finder`/`scandir` used for code generation (FS order is inode-dependent).
3. **No wall-clock timestamps inside emitted content** — except a single explicit `generated_at` / `duration_ms` field where one is genuinely useful.
4. **No machine identifiers** — no hostname, username, or absolute paths.
5. **No nondeterministic randomness** — derive any UUID from the spec SHA (`uuid5`), never `random_*`.

Enforcement is layered and live:

- **In-process tests** — `tests/Determinism/TwiceHarness.php` is the shared harness, and `tests/AgentSpec/Determinism/`, `tests/Scaffold/Determinism/` ship "run twice, diff empty" tests for the manifest pipeline, the OpenAPI fragment merger, and the TypeScript + Python SDK emitters. Add one anywhere you add a new emitter.
- **CI determinism gate** — `.github/workflows/ci.yml` regenerates `.agent/` and `git diff --exit-code`s it on every PR. A non-deterministic emitter fails the gate.
- **`bin/altair doctor`** — the `determinism_check` from `univeros/doctor` runs the same regenerate-and-diff gate against any generators a host configures (see `DoctorConfiguration`'s constructor).
- **Skeleton workflow** — `src/Altair/Bootstrap/resources/skeleton/.github/workflows/determinism.yml` ships with `bin/altair new`, so generated host projects inherit the gate.

**Any new package added to the framework must meet this standard.** Tracked in [#74](https://github.com/univeros/framework/issues/74).

---

## 6. Testing

- Framework: **PHPUnit 11**, attribute-style (`#[DataProvider]`, `#[Group]`, `#[Test]`).
- Bootstrap: `tests/bootstrap.php`. Per-package fixtures in `tests/{Container,Courier,Sanitation,Validation}/fixtures.php`.
- Suffix `Test.php`, mirror the `src/Altair/<Pkg>` layout under `tests/<Pkg>/`.
- Coverage target: **80%+** for new code (unit + integration).
- Integration tests against Redis/Memcached/MongoDB use real services. CI starts containers; locally use Docker.

```bash
vendor/bin/phpunit                              # full suite
vendor/bin/phpunit --filter CookieManagerTest   # one test class
vendor/bin/phpunit --testsuite "Univeros Test Suite"
```

---

## 7. Modernization status (started 2026-05)

The codebase is mid-migration from PHP 7.2 / abandoned deps to PHP 8.3. Phases 1, 2, 3a–3c are **done**; Phase 3d is all but PSR-14 (tracked in [#97](https://github.com/univeros/framework/issues/97)); Phase 4 is in progress (PHPStan at level 6, burn-down tracked in [#96](https://github.com/univeros/framework/issues/96); PHPUnit attribute migration done).

### Phase 1 — COMPLETE

- Composer manifest rewritten (root + 16 sub-packages), `,,` syntax error fixed.
- Dep majors bumped (see §4). PHP floor `>=8.3`.
- `Zend\Diactoros\*` → `Laminas\Diactoros\*` swapped in 8 files.
- `.travis.yml` → `.github/workflows/ci.yml` (8.3 + 8.4 matrix).
- `.php_cs` → `.php-cs-fixer.dist.php` (v3 format, PER-CS 2.0).
- `phpunit.xml.dist` → PHPUnit 11 schema.
- `phpstan.neon.dist` + `rector.php` added.
- `.pre-commit-config.yaml` updated.
- `.gitignore` covers new tool caches.

### Phase 2 — COMPLETE (Rector automated)

`composer rector:fix` has been applied across the codebase; `rector process --dry-run` is **green on master** and runs in CI's `static-analysis` job (note: it is **not** part of `composer qa`, so run it manually before pushing). All the configured sets are satisfied — constructor promotion, native types from PHPDoc, `match`, nullsafe/`??=`/first-class-callables/`str_contains`, dead-code/early-return/`instanceof` simplifications, and `PRIVATIZATION`.

PHP 8.4's `ExplicitNullableParamTypeRector` was added (target lifted to 8.4 to arm it) so implicit-nullable params (`Type $x = null`) stay fixed going forward — see [#93](https://github.com/univeros/framework/issues/93).

One rule is **deliberately skipped** in `rector.php`: `NewInInitializerRector`. Collapsing `?? new X()` bodies into promoted `= new X()` defaults narrows public constructor contracts (an explicit `null` arg becomes a TypeError); declined as a needless BC change. Remove the skip to adopt it intentionally.

### Phase 3a — COMPLETE (HTTP middleware to PSR-15)

All `Altair\Http\Middleware\*` classes migrated:

- `Altair\Http\Contracts\MiddlewareInterface` now `extends Psr\Http\Server\MiddlewareInterface`; keeps the `ATTRIBUTE_*` typed class constants (PHP 8.3).
- All 14 middleware implement `process(ServerRequestInterface, RequestHandlerInterface): ResponseInterface`. Short-circuiting middleware receive `ResponseFactoryInterface` via constructor injection.
- `relay/middleware` v1 adapters (`AbstractContentHandlerMiddleware`, `FormContentMiddleware`, `JsonContentMiddleware`) are reimplemented inline — the package is gone in Relay 2.
- `Altair\Http\Resolver\ContainerResolver` no longer implements the removed `Relay\ResolverInterface`; it's a plain `__invoke(object|string): object` callable, which is what Relay 2 accepts.
- `Altair\Http\Configuration\RelayConfiguration` uses `new Relay($queue->toArray(), $resolver)` (Relay 2 ctor).
- `tests/Http/Middleware/AbstractMiddlewareTest::dispatch()` builds a PSR-15 pipeline using `Relay\Relay::handle()` with an anonymous terminal handler.
- `Altair\Http\Responder\{Compound,Formatted}Responder` typehint the resolver as `callable` instead of `Relay\ResolverInterface`.
- **Bug fix as part of migration:** `CsrfMiddleware` previously returned 403 on *valid* tokens (`if ($isPost && validate)` — inverted). Corrected to `if ($unsafeMethod && !validate)`.

Decorator middleware that depend on the next response (CORS, cache headers) must now run **before** terminal middleware (ActionMiddleware) in the queue so they wrap the response that bubbles back up.

### Phase 3b — COMPLETE (Dotenv v2 → v5)

- `Altair\Configuration\EnvironmentConfiguration` now uses `Dotenv\Dotenv::createImmutable($dir, $file)` (or `createMutable` when constructed with `$immutable = false`). The `Dotenv\Loader` class is internal in v5 and no longer used directly.
- `tests/Configuration/EnvironmentConfigurationTest` migrated from PHPUnit 7 annotation-based exception expectations (`@expectedException`, `@expectedExceptionMessageRegExp`) to method-based (`expectException()`, `expectExceptionMessageMatches()`).

### Phase 3c — COMPLETE (Flysystem v1 → v3)

- **Deleted** configurations for adapters removed in Flysystem v3: Rackspace, Azure, WebDAV, ZipArchive, GridFs.
- **Rewrote** the survivors against v3 adapter constructors: `LocalFilesystemAdapter`, `FtpAdapter` (uses `FtpConnectionOptions::fromArray`), `AwsS3V3Adapter`, `SftpAdapter` (uses `SftpConnectionProvider`), Spatie `DropboxAdapter`.
- `Altair\Filesystem\Contracts\FilesystemAdapterInterface` now extends `League\Flysystem\FilesystemOperator` (was `FilesystemInterface` in v1).
- `Altair\Filesystem\Adapter\FlysystemAdapter` rewritten as a thin explicit decorator (no more magic `__call`) wrapping a `FilesystemOperator`. All v3 methods are forwarded; `exists/prepend/append/listDirectories` rewritten against v3's StorageAttributes/DirectoryListing iterables.
- `FilesystemAdapterConfiguration` no longer wires a `CachedAdapter` — Flysystem v3 removed caching from core. Wrap with a caching decorator separately if needed.
- The `FlysystemAdapter.php` exclusion from PHPUnit coverage, PHPStan, and Rector is removed — the new implementation is testable.

### Phase 3d — MOSTLY COMPLETE (targeted modern idioms)

- Value objects → `readonly` — **done** (148 `readonly` classes in `src/`).
- Sentinel `class const` → **enums** — **done** (8 enums in `src/`).
- Rector-driven cleanup of PHPDoc-only types — **done** (`TYPE_DECLARATION` set applied; `rector --dry-run` clean).
- **Remaining:** `Altair\Happen\*` PSR-14. The dep + `StoppableEventInterface` are already wired, but the dispatcher/provider are name-based, not PSR-14's object-based interfaces — tracked in [#97](https://github.com/univeros/framework/issues/97) (it's a design choice, deferred deliberately).

### Phase 4 — IN PROGRESS (static analysis + test attribute migration)

1. **PHPStan level 5 → 8.** Now at **level 6** (raised in [#95](https://github.com/univeros/framework/pull/95)) with a regenerated `phpstan-baseline.neon` grandfathering 746 errors (mostly missing `array<K,V>` value-type shapes). Burn-down of the baseline and the 6 → 7 → 8 raises are tracked in [#96](https://github.com/univeros/framework/issues/96). Don't hand-edit the baseline — regenerate with `vendor/bin/phpstan analyse --generate-baseline`.
2. PHPUnit annotation → attribute migration — **done**. The suite already uses `#[DataProvider]` (157 files) / `#[CoversClass]` (43 files); the only remaining `@covers` is `tests/TestReporter/Fixtures/LegacyCoversAnnotationTest.php`, an intentional fixture exercising the test-reporter's legacy-annotation fallback.
3. Verify coverage ≥ 80% per sub-package. (Still a standing goal.)

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

If Rector or PHPStan find new issues your change introduced, fix at root cause — don't add `ignoreErrors` entries without justification in a comment.

### What's risky in this codebase

- **Middleware signature mismatch:** Phase 3 is incomplete. Some classes still implement `Altair\Http\Contracts\MiddlewareInterface` (old double-pass). Treat as in-flight.
- **Flysystem configurations:** Several `*AdapterConfiguration` classes reference removed adapters and will throw at runtime until Phase 3c lands.
- **No working `composer.lock`** until the user runs `composer update` post-Phase-1.

---

## 9. References

- PSR specs: <https://www.php-fig.org/psr/>
- PHP 8.3 release notes: <https://www.php.net/releases/8.3/en.php>
- Rector docs: <https://getrector.com/>
- PHPStan rule levels: <https://phpstan.org/user-guide/rule-levels>
- PHPUnit 11 attributes: <https://docs.phpunit.de/en/11.4/attributes.html>
- Flysystem v3 migration: <https://flysystem.thephpleague.com/docs/upgrade-from-1.x/>
- Laminas Diactoros (PSR-7): <https://docs.laminas.dev/laminas-diactoros/>
- Relay 2 (PSR-15): <https://github.com/relayphp/Relay.Relay>
- Dotenv 5: <https://github.com/vlucas/phpdotenv#usage>
