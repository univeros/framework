# AGENT.md — vendor/module

Agent guide for this package. It is a **Univeros module**: a pluggable, installable
feature that a host app wires in with one line in `config/modules.php`. This file
is the orientation an agent should read **before** editing — the human-facing
README is `README.md`.

## The one rule

Use **this package's own vendor and namespace** everywhere. Never rename things to
`Altair\*` (the framework's first-party namespace) or to a `univeros/*` package name
(those are the framework's read-only splits). The scaffolder already set the correct
name and namespace — keep them.

## What a module is

`src/Module.php` implements `Altair\Module\Contracts\ModuleInterface` (a
`ConfigurationInterface` + `name()`) and **opts into capabilities by also
implementing the narrow provider contracts** — implement only what you ship:

| Contract | Method | Contributes |
|---|---|---|
| `RoutesProviderInterface` | `routes()` | HTTP routes |
| `MiddlewareProviderInterface` | `middleware()` | PSR-15 middleware, ordered by priority |
| `EntityDirectoriesProviderInterface` | `entityDirectories()` | Cycle entity dirs |
| `MigrationDirectoriesProviderInterface` | `migrationDirectories()` | DB migrations |

Drop a capability by removing its interface from the `implements` list. A
service-only module needs just `ModuleInterface` and `univeros/module`.

## The HTTP lifecycle

Endpoints follow **Action -> Input(DTO) -> Domain -> Responder**. The generated
`VendorModule\Http\Actions\SampleAction` + `SampleInput` + `SampleResponder` +
`VendorModule\Domain\SampleService` are the canonical pattern — copy their shape
for new endpoints. The Action stays thin: validate via the Input, call the Domain,
hand the result to the Responder.

## Conventions (non-negotiable)

- `declare(strict_types=1);` in **every** PHP file.
- **Immutability** — never mutate value objects; return new copies via `withX()`.
- **Native types** over PHPDoc; add PHPDoc only for `array<K,V>` shapes / unions PHP
  can't express.
- **Many small files** (200-400 LOC typical), organized by feature.
- **Tests first**, 80%+ coverage on new code. No new code without a test.

## Develop and test in isolation (no host app needed)

```bash
composer install
vendor/bin/phpunit
```

`tests/ModuleTest.php` constructs the module and asserts its routes, bindings, and
directories. Grow it as you add behaviour — the host is never involved to test a
module in isolation.

## Scaffolding endpoints — important caveat

The `bin/altair spec:scaffold` YAML flow (write a spec, emit the Action/Input/
Responder/Domain/test) lives in the **framework**, not in this package's
dependencies. So inside this module:

- The spec YAML **vocabulary is not vendored here** — do not guess it. Confirm with
  `bin/altair spec:show <spec>` against a framework install before relying on it.
- If `bin/altair` is unavailable, **hand-write** the Action/Input/Responder triple
  following `SampleAction` rather than inventing a structure.

## How a host installs this module

```php
// host app: config/modules.php
return [
    new VendorModule\Module(),
];
```

Routes, middleware, and migrations are then picked up automatically. Entities need
one host binding: `SchemaProviderInterface` -> `ModuleAwareSchemaProvider`.

## Publish

An ordinary Composer package — tag a release and submit to Packagist. Keep your own
vendor/namespace (see "The one rule").

## Canonical docs

- Building a module: <https://github.com/univeros/framework/blob/master/docs/guides/extending.md>
- Module contract reference: <https://github.com/univeros/framework/blob/master/docs/packages/module.md>
