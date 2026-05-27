# Altair Framework documentation

Per-package guides for the 18 sub-packages bundled in `univeros/framework`. Each page stands alone — a developer who lands on it via search should be productive with the package after reading it, without opening the source.

The framework targets PHP 8.3+, follows PSR-7/15/14/6/16 where applicable, and is composed of independently usable packages. You can install the meta-package (`composer require univeros/framework`) or pick the pieces you need (`composer require univeros/cookie`, etc.).

## Packages

### HTTP stack

The request/response lifecycle and everything that runs inside it.

- [Http](./packages/http.md) — PSR-15 middleware pipeline with the framework's signature Action / Domain / Input / Responder request lifecycle, FastRoute integration, and JWT / basic / digest auth middleware.
- [Cookie](./packages/cookie.md) — readonly value objects for `Cookie` / `Set-Cookie`, plus a manager that round-trips them through PSR-7 messages.
- [Session](./packages/session.md) — server-side session storage with File / Mongo / PDO / Predis handlers, paired with the cookie envelope and HTTP cache limiters.
- [Sanitation](./packages/sanitation.md) — sixteen input filters (Alpha, Boolean, Integer, Regex, …) that normalise raw values into safe canonical forms before validation runs.
- [Validation](./packages/validation.md) — eighteen rule-based input validators (Email, IBAN, ZipCode, …) composable into rule collections and runnable through a `Validator`.

### Application core

Cross-cutting building blocks the rest of the framework — and your app — wire into.

- [Container](./packages/container.md) — auto-wiring DI container with reflection caching, `define` / `share` / `alias` / `prepare` / `delegate` bindings, and DI-aware executable invocation.
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
- [Persistence](./packages/persistence.md) — thin Repository / UnitOfWork contract over Cycle ORM v2, with `bin/altair db:*` migration commands and an optional `persistence:` block on endpoint specs.
- [Security](./packages/security.md) — cryptographic primitives — HKDF / PBKDF2 key derivation, an encryption envelope with double-HMAC MAC, and salt generation.

## How these docs are structured

Every package page follows the same skeleton: a one-sentence pitch, an introduction in prose, installation, quick start, concepts, usage, testing notes, optional recipes, and links to related packages. Code examples are runnable against the package's published API and are kept in sync with the source as part of the same PR.

If you spot a drift between the documented behaviour and what the code does, please open an issue at https://github.com/univeros/framework/issues — the source is the source of truth, but the docs are a contract too.
