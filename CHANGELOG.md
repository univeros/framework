# Changelog

All notable changes to `univeros/framework` are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Each released version below corresponds to the annotated git tag of the same
name, split-published to the read-only `univeros/*` package mirrors. Changes that
have merged to `master` but are not yet tagged live under **\[Unreleased]**.

## [Unreleased]

### Added
- `bin/altair module:new` now scaffolds an `AGENT.md` agent guide alongside the
  README, so an agent working in a generated module gets the Univeros conventions
  up front: the naming rule, the `ModuleInterface` capability contracts, the
  Action/Input/Domain/Responder lifecycle, the isolated dev/test loop, and the
  `spec:scaffold` vocabulary caveat. (#230, #231)

## [2.5.1] - 2026-06-06

### Fixed
- `univeros/http` and `univeros/cookie` pinned `psr/http-message ^2.0`, which made
  them uninstallable as standalone packages; their own deps `neomerx/cors-psr7`
  (`^1.0`) and `relay/relay` (`~1.0`) cap the graph at PSR-7 v1.1. Loosened both to
  `^1.1 || ^2.0` (matching the monorepo root and the sibling HTTP packages), so
  `composer require univeros/http` and `bin/altair module:new` modules resolve. No
  code change; the framework already runs on psr/http-message 1.1. (#228, #229)

### Changed
- Test toolchain: PHPUnit 11 → 12.5 and paratest 7.8 → 7.20, keeping the PHP 8.3
  floor. `univeros/test-reporter`'s `require` moved to `phpunit ^12.5`. PHPUnit 13 /
  paratest 7.22+ require PHP 8.4 and are deferred. (#209, #226)

## [2.5.0] - 2026-06-06

### Added
- `MiddlewareProviderInterface`: modules self-register PSR-15 middleware into the
  HTTP pipeline, ordered by documented priority anchors (`MiddlewarePriority`) and
  merged deterministically via `ModuleMiddleware`. (#224, #225)
- OpenAPI 3.1 bidirectional fidelity (epic #214): parameter mapping, validation
  constraints ↔ rules, non-JSON object bodies/responses, `allOf` flattening,
  `oneOf`/`anyOf`/`additionalProperties` surfacing, external `$ref` bundling on
  import, and noun-based RPC action paths. (#215–#222)

### Fixed
- `openapi:import` maps top-level array request bodies. (#212, #213)

## [2.4.1] - 2026-06-04

### Fixed
- `openapi:import` disambiguates operations that derive the same filename (e.g. the
  Petstore's `updatePet` / `updatePetWithForm`) by falling back to their
  `operationId` instead of failing with a collision. (#210)

## [2.4.0] - 2026-06-04

### Added
- Nested objects & arrays-of-objects in spec inputs: bidirectional OpenAPI
  import/export, round-trip clean (the Swagger Petstore imports with zero skips).
  New `openapi:import --skip-unmappable` for graceful partial imports. (#203, #204)
- paratest (`composer test:par`) plus a dependency-free docker-CLI integration
  harness. (#129, #208)

### Fixed
- Order generated Input DTO constructor params required-first, avoiding PHP's
  "required parameter after optional" deprecation. (#205, #206)

### Changed
- AGENT.md + CLAUDE.md refreshed to current state: 40 sub-packages, modernization
  (Phases 1–4) complete, PHPStan level 8 with no baseline. (#207)

## [2.3.0] - 2026-06-03

### Added
- `univeros/logging` (`Altair\Logging`): a PSR-3 `LoggerInterface` backed by Monolog,
  wired from `LOG_*` env vars; newline-delimited JSON to stderr by default, human
  format opt-in. Wired into the skeleton so a fresh app logs out of the box. (#202)

## [2.2.0] - 2026-06-03

### Added
- Production-safe, agent-native HTTP error handling: `HttpExceptionInterface` (thrown
  exceptions render with their real status (404 no longer collapses to 500),
  `ProblemDetailsErrorHandler` (RFC 7807 problem+json, debug vs production verbosity),
  and an `EventRecordingLogger` bridge recording 5xx failures as `http_error` events.
  Wired into the skeleton by default. (#200)

## [2.1.0] - 2026-06-03

### Added
- Module (extension) system: a host registers one class and a feature's routes,
  entities, and migrations self-register (`univeros/module` + `bin/altair module:new`).

### Changed
- Docs reorganised into a two-tier layout (`docs/guides/`).

## [2.0.2] - 2026-06-02

### Fixed
- Accurate package descriptions; `univeros/univeros` starter name fix.

## [2.0.1] - 2026-06-02

### Added
- Split-pipeline parity: `univeros/idempotency` and `univeros/webhooks` published.

## [2.0.0] - 2026-05-30

### Added
- First Packagist-published release. Brings the framework from the 2017-era PHP 7.2
  codebase to PHP 8.3+: PSR-7/15 single-pass middleware, readonly value objects,
  native types, and 35+ standalone `univeros/*` packages.

[Unreleased]: https://github.com/univeros/framework/compare/v2.5.1...master
[2.5.1]: https://github.com/univeros/framework/compare/v2.5.0...v2.5.1
[2.5.0]: https://github.com/univeros/framework/compare/v2.4.1...v2.5.0
[2.4.1]: https://github.com/univeros/framework/compare/v2.4.0...v2.4.1
[2.4.0]: https://github.com/univeros/framework/compare/v2.3.0...v2.4.0
[2.3.0]: https://github.com/univeros/framework/compare/v2.2.0...v2.3.0
[2.2.0]: https://github.com/univeros/framework/compare/v2.1.0...v2.2.0
[2.1.0]: https://github.com/univeros/framework/compare/v2.0.2...v2.1.0
[2.0.2]: https://github.com/univeros/framework/compare/v2.0.1...v2.0.2
[2.0.1]: https://github.com/univeros/framework/compare/v2.0.0...v2.0.1
[2.0.0]: https://github.com/univeros/framework/releases/tag/v2.0.0
