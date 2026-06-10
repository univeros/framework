<h1 align="center">Univeros Framework</h1>

<p align="center">
  <em>The source code of the Univeros framework. Namespace: <code>Altair\*</code>. Ships as <code>composer require univeros/framework</code>.</em>
</p>

<p align="center">
  <a href="https://github.com/univeros/framework/actions/workflows/ci.yml"><img src="https://github.com/univeros/framework/actions/workflows/ci.yml/badge.svg" alt="CI"></a>
  <a href="https://opensource.org/licenses/MIT"><img src="https://img.shields.io/badge/license-MIT-blue.svg" alt="License: MIT"></a>
  <img src="https://img.shields.io/badge/php-%3E%3D8.3-777BB4.svg" alt="PHP 8.3+">
  <img src="https://img.shields.io/badge/packages-36-success.svg" alt="36 packages">
</p>

---

> **Note:** this repository contains the core code of Univeros. If you want to build an application using Univeros, visit the main [**univeros/univeros**](https://github.com/univeros/univeros) repository.

## About Univeros

Univeros is a PHP 8.3+ framework for building APIs. Its codebase lives under the `Altair\*` namespace, the engineering name, the way `Illuminate\*` is the engineering name for Laravel's components. The brand consumers see is **Univeros**; `Altair\*` is the plumbing.

It looks familiar at first: PSR-7/15 HTTP stack, a DI container, a Cycle ORM bridge, a Symfony Messenger bridge, immutable value objects, single-pass middleware. The unusual part is the layer above that: a CLI surface (`bin/altair`) whose every command emits deterministic JSON an AI agent can branch on, and a set of primitives (**spec-driven scaffolding, a rewindable journal, an append-only event log, a symbol-usage index, a doctor, a refactor adviser, an MCP server**) designed so an agent can be productive without a human in the loop.

For the pitch, agent affordances walkthrough, and architecture diagram, see [**univeros/univeros**](https://github.com/univeros/univeros). For per-package guides, see [**univeros/docs**](https://github.com/univeros/docs).

## Sub-packages

The framework is composed of 36 standalone PHP packages under [src/Altair/](src/Altair/). Each is published as a read-only repository at `github.com/univeros/<name>`. Pull the whole framework via:

```bash
composer require univeros/framework
```

…or compose individual packages:

```bash
composer require univeros/http          # PSR-7 + PSR-15 stack, single-pass middleware
composer require univeros/scaffold      # YAML spec → Action/Input/Responder + OpenAPI + tests
composer require univeros/persistence   # Repository/UnitOfWork bridge over Cycle ORM v2
composer require univeros/messaging     # MessageBus bridge over Symfony Messenger
composer require univeros/events        # Append-only mutation event log for agents
```

The complete published list: `agent-spec`, `bootstrap`, `cache`, `cli`, `common`, `configuration`, `container`, `cookie`, `courier`, `data`, `doctor`, `eval`, `events`, `examples`, `filesystem`, `happen`, `http`, `index`, `introspection`, `mcp`, `messaging`, `middleware`, `migration-intelligence`, `observability`, `observatory`, `persistence`, `profiling`, `sanitation`, `scaffold`, `security`, `session`, `structure`, `suggest`, `test-reporter`, `tinker`, `validation`.

Splits are produced automatically by [.github/workflows/split.yml](.github/workflows/split.yml); see [docs/guides/split-publish.md](docs/guides/split-publish.md) for the operator runbook. All changes belong in this monorepo; the split repos are read-only mirrors.

## Repositories

- **[univeros/univeros](https://github.com/univeros/univeros)**: `composer create-project` starter and the main entry point for application developers.
- **[univeros/framework](https://github.com/univeros/framework)**: this repo. The library source.
- **[univeros/docs](https://github.com/univeros/docs)**: per-package documentation.

## Contributing

Issues and pull requests are welcome on this repository; it's the source of truth. The 35 sub-package repos under `github.com/univeros/*` are read-only mirrors; PRs against them will be ignored and overwritten on the next split.

Before submitting:

```bash
composer qa     # cs + stan + test — the pre-commit gate
composer test   # PHPUnit 12 only
```

CI mirrors the same gates plus the determinism drift check. Add a bullet to the [`CHANGELOG.md`](CHANGELOG.md) `[Unreleased]` section for any user-facing change.

## Security

If you discover a security vulnerability, please **report it privately via [GitHub Security Advisories](https://github.com/univeros/framework/security/advisories/new)** instead of opening a public issue. We will respond and coordinate disclosure from there.

## License

Univeros is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
