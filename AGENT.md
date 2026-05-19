# AGENT.md

Canonical, vendor-neutral guide for AI coding agents (Claude Code, Cursor, Aider, Copilot, etc.) working on the **Univeros / Altair Framework**.

This file is the source of truth. Tool-specific entry points (`CLAUDE.md`) point here.

---

## 1. Project at a glance

- **Name:** `univeros/framework` — PHP framework, MIT licensed.
- **Root namespace:** `Altair\*` (legacy reasons; the Composer package is `univeros/*` and 16 sub-packages are bundled via `replace`).
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
│   ├── Filesystem/    ← Flysystem v3 adapters & configuration
│   ├── Happen/        ← Event dispatcher
│   ├── Http/          ← PSR-7/15 stack: routing (FastRoute), middleware, CORS, JWT, content negotiation
│   ├── Middleware/    ← PSR-15 middleware primitives
│   ├── Sanitation/    ← Input sanitation rules
│   ├── Security/      ← Hashing, encryption, CSRF tokens
│   ├── Session/       ← Session handlers (file, Redis, Mongo)
│   ├── Structure/     ← Collection primitives (Map, Set, etc.)
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

The codebase is mid-migration from PHP 7.2 / abandoned deps to PHP 8.3. Phase 1 (tooling baseline) is **done**; Phases 2-4 are the remaining work.

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

### Phase 2 — PENDING (Rector automated)

Run `composer rector:fix` after `composer update`. Rector will apply:

- Constructor property promotion (~all classes with N-arg constructors that assign to `$this->x = $x`).
- Native param/return/property types from PHPDoc.
- `match` expressions replacing `switch` chains.
- Nullsafe `?->`, `??=`, first-class callables, `str_contains/starts_with/ends_with`.
- Dead code removal, early returns, `instanceof` simplifications.
- Final classes/private methods/properties where safe (`PRIVATIZATION` set).

**After Rector, run `composer cs:fix && composer test`.** Expect some Rector edits to need manual cleanup.

### Phase 3 — PENDING (manual breaking-change migrations)

These cannot be done by Rector. File-level scope:

| File(s) | Migration |
|---|---|
| `src/Altair/Http/Configuration/RelayConfiguration.php` | Relay v1 `RelayBuilder` → Relay v2 `new Relay(array $queue)` (PSR-15) |
| `tests/Http/Middleware/AbstractMiddlewareTest.php` | Same — and middlewares now implement `Psr\Http\Server\MiddlewareInterface::process()` |
| `src/Altair/Configuration/EnvironmentConfiguration.php` | `new Dotenv($path, $file)` → `Dotenv::createImmutable($path, $file)->load()` (immutability via `safeLoad()` if needed) |
| `src/Altair/Filesystem/Configuration/{Rackspace,Azure,WebDAV,ZipArchive,GridFs}AdapterConfiguration.php` | **Delete** — adapters removed in Flysystem v3 |
| `src/Altair/Filesystem/Configuration/{AwsS3,Local,Sftp,Ftp,Dropbox}AdapterConfiguration.php` | Rewrite for Flysystem v3 adapter constructors (signatures changed) |
| `src/Altair/Filesystem/Contracts/FilesystemAdapterInterface.php` | Align with `League\Flysystem\FilesystemAdapter` (v3) |
| Various PSR-7 consumers | PSR-7 v2 added return types — implementations may need signature updates |
| Value objects (CacheItem, Cookie, immutable DTOs) | Convert to `readonly` props / `readonly class` (PHP 8.2+) |
| Sentinel `class const` constants representing closed sets | Promote to **enums** (`BackedEnum`) |
| Middleware contracts | Replace `Altair\Http\Contracts\MiddlewareInterface` with `Psr\Http\Server\MiddlewareInterface` where it's a true PSR-15 middleware |

### Phase 4 — PENDING (static analysis + test attribute migration)

1. Raise `phpstan.neon.dist` `level` from 5 → 8 incrementally. Fix or `ignoreErrors` each error.
2. PHPUnit annotation → attribute migration:
   - `@dataProvider foo` → `#[DataProvider('foo')]`
   - `@group slow` → `#[Group('slow')]`
   - `@test` → `#[Test]`
   - `@covers` → `#[CoversClass(...)]`
3. Verify coverage ≥ 80% per sub-package.

---

## 8. Working in this repo

### Where to start

- **Adding a feature inside an existing sub-package:** read that package's `Contracts/`, then its concrete classes. Configurations live in `Configuration/`.
- **Adding a new sub-package:** mirror the layout of `src/Altair/Structure/` (smallest, cleanest). Add a `composer.json`, register in root `replace`, add an autoload entry.
- **Touching HTTP/middleware:** PSR-15 single-pass only. Implement `Psr\Http\Server\MiddlewareInterface`.
- **Touching cache:** PSR-6 (`CacheItemPoolInterface`) is the primary contract; PSR-16 is a façade over it.

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
