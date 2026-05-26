# Altair Framework documentation

Per-package guides for the 16 sub-packages bundled in `univeros/framework`. Each page stands alone — a developer who lands on it via search should be productive with the package after reading it, without opening the source.

The framework targets PHP 8.3+, follows PSR-7/15/14/6/16 where applicable, and is composed of independently usable packages. You can install the meta-package (`composer require univeros/framework`) or pick the pieces you need (`composer require univeros/cookie`, etc.).

## Packages

### HTTP stack

- [Cookie](./packages/cookie.md) — readonly value objects for `Cookie` / `Set-Cookie`, plus a manager that round-trips them through PSR-7 messages.

### Application core

_Coming soon: container, configuration, happen, courier, middleware._

### Data & domain

_Coming soon: data, validation, structure, common, sanitation._

### Infrastructure

_Coming soon: cache, filesystem, security, session, http._

## How these docs are structured

Every package page follows the same skeleton: a one-sentence pitch, an introduction in prose, installation, quick start, concepts, usage, testing notes, optional recipes, and links to related packages. Code examples are runnable against the package's published API and are kept in sync with the source as part of the same PR.

If you spot a drift between the documented behaviour and what the code does, please open an issue at https://github.com/univeros/framework/issues — the source is the source of truth, but the docs are a contract too.
