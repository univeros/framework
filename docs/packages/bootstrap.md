# Bootstrap

> Zero-to-running project bootstrap — the `bin/altair new` command that materialises a complete, runnable Altair API (one working endpoint, one passing test, the whole spec-driven toolchain wired) from a single command.

**Composer:** `univeros/bootstrap`
**Namespace:** `Altair\Bootstrap`

## Introduction

Every new project starts the same way: a directory layout, a `composer.json`, a PHPUnit config, a container boot file, route registration, env defaults. Hand-rolling those is busywork — and for an AI agent it is wasted tokens and a source of inconsistency before any real work begins. This package removes that step. You run `bin/altair new`, and you get a project that boots, tests cleanly, and serves `GET /ping` returning `200` — a known-good launchpad that every subsequent `spec:scaffold` extends.

The package is small and deliberately so: a **skeleton template** (the files a new project needs), a **`SkeletonGenerator`** that copies and customises it, a handful of **presets** that answer the "which ORM / which queue" questions non-interactively, and the **`new` command** that ties them together. The generated `/ping` endpoint is real — it flows through the same `Action → Domain → Responder` pipeline ([http.md](./http.md)) and the same typed-DTO input handling every scaffolded endpoint uses, so it doubles as living proof that the framework is wired correctly.

What this package does *not* do: it does not run `composer install`, apply migrations, or boot a server for you — those need the network and a chosen runtime, so the command prints them as next steps rather than performing them. It also does not generate frontend scaffolding or deployment manifests.

## Installation

The bootstrap command ships with the framework, so if you installed `univeros/framework` it is already on `bin/altair`. Standalone:

```bash
composer require univeros/bootstrap
```

It depends on [cli.md](./cli.md) (the command substrate), [container.md](./container.md), and [configuration.md](./configuration.md). No PHP extensions beyond core PHP 8.3.

## Quick start

Generate a project into `my-api` with the recommended preset:

```bash
bin/altair new --dir=my-api --preset=standard
```

That writes ~20 files and prints the next steps. Bring it to life:

```bash
cd my-api
composer install
composer serve            # php -S localhost:8080 -t public
curl localhost:8080/ping  # {"message":"ok","timestamp":"..."}
composer test             # the shipped PingActionTest + PingTest pass
```

Generate non-interactively (e.g. in a script or from an agent), choosing the smallest runnable shape:

```bash
bin/altair new --dir=/tmp/scratch --preset=minimal --no-interaction
```

## Concepts

Four small pieces, each independently usable:

- **Skeleton template** — the project files, shipped inside the package at `resources/skeleton`. It travels with `univeros/bootstrap` when the monorepo is split, so the generator always finds it via `SkeletonGenerator::defaultSkeletonPath()`. The template uses the `App\` namespace and a `vendor/app` package name as placeholders. The skeleton also ships **`.claude/skills/altair/SKILL.md`** — the lazy-loaded Claude Code skill that teaches shell-capable agents to drive the project through `bin/altair` instead of pulling every MCP tool's schema into context every turn (#131); generated projects get it pre-installed so any agent that opens them sees the skill in its available-skills list.
- **`SkeletonGenerator`** — recursively copies the template into a target directory, rewriting the `App\` namespace and the composer package name on the way. It refuses to overwrite a non-empty directory unless you pass `force`.
- **Presets** (`Profile\MinimalPreset`, `StandardPreset`, `FullPreset`, behind `Contracts\PresetInterface`) — a named bundle of the two choices the interactive command would otherwise prompt for: which ORM (`cycle` / `doctrine` / `pdo` / `none`) and which queue transport (`redis` / `doctrine` / `sync` / `none`). `PresetRegistry` resolves them by name and rejects unknown names.
- **Steps** (`Step\GenerateEnvStep`) — the post-copy customisation. `GenerateEnvStep` writes `.env` from `.env.example`, setting the messenger DSN to match the preset; secrets stay as obvious placeholders (`APP_KEY=changeme`) rather than being auto-generated.

The presets:

| Preset | ORM | Queue | Use it when |
|---|---|---|---|
| `minimal` | none | sync | You want the smallest runnable project — no database, no broker. |
| `standard` | cycle | redis | The recommended default for a real API. |
| `full` | cycle | redis | Same as standard, with the agent-spec + MCP tooling expected to be pre-wired. |

## What the generated project looks like

```
my-api/
├── composer.json            # type: project, requires univeros/framework
├── .env.example / .env      # non-secret defaults; APP_KEY=changeme placeholder
├── phpunit.xml.dist
├── phpstan.neon.dist
├── .php-cs-fixer.dist.php
├── public/index.php         # front controller: FastRoute + Relay + Action pipeline
├── config/
│   ├── container.php         # builds the container, applies the Configuration chain
│   ├── configurations.php    # the Configuration chain (empty for minimal)
│   └── routes.php            # [METHOD, PATH, Action::class] table
├── app/                      # your code (App\ namespace)
│   ├── Http/{Actions,Inputs,Responders}/Ping*.php
│   └── Health/Ping.php       # the one fully-implemented domain
├── api/ping.yaml             # the proof-of-life spec
├── tests/{Http,Feature}/     # PingActionTest + an in-process PingTest
├── docs/openapi/ping.yaml
└── database/migrations/
```

The front controller wires the canonical pipeline — `DispatcherMiddleware` (FastRoute) then `ActionMiddleware` — through `Relay`, exactly as the framework's own integration tests do. Because `ActionMiddleware` hydrates typed DTO inputs (see [http.md](./http.md)), the scaffolded `Action → Domain → Responder` shape runs without further glue.

## Usage

### Choosing a name and namespace

The defaults are the `App\` namespace and the `vendor/app` package name. Override both:

```bash
bin/altair new --dir=acme-api --name=acme/api --namespace=Acme --preset=standard
```

The generator rewrites `namespace App` → `namespace Acme`, the `App\` use-statements and FQNs in every `.php` file, and the `composer.json` `autoload.psr-4` key.

### Regenerating into an existing directory

By default the command refuses to write into a non-empty directory, so you can't clobber work by accident. Pass `--force` to overwrite:

```bash
bin/altair new --dir=my-api --force
```

### Driving generation from your own tooling

The pieces are plain services. Generate a project from a script or test without the CLI:

```php
use Altair\Bootstrap\Profile\PresetRegistry;
use Altair\Bootstrap\SkeletonGenerator;
use Altair\Bootstrap\Step\GenerateEnvStep;

$created = (new SkeletonGenerator())->generate('/path/to/target', namespace: 'Acme', projectName: 'acme/api');

(new GenerateEnvStep())->run('/path/to/target', (new PresetRegistry())->get('standard'));
```

`generate()` returns the list of created paths (relative to the target), so you can report or assert on exactly what was written.

### Building the next endpoint

Once the project is installed, the toolchain is the spec-driven flow from [scaffold.md](./scaffold.md):

```bash
vendor/bin/altair spec:scaffold api/your-endpoint.yaml
```

Implement the generated domain's `__invoke()` — the one piece left as a TODO — and the endpoint is live, just like `app/Health/Ping.php`.

## Configuration

There is no `Configuration` class to wire: `NewCommand`, `SkeletonGenerator`, `PresetRegistry`, and `GenerateEnvStep` are plain `readonly` services with new-on-default constructor arguments, and `bin/altair` auto-discovers the command via the [cli.md](./cli.md) attribute scanner (it adds `src/Altair/Bootstrap/Cli` to the command path at startup). Pass a custom `PresetRegistry` to `NewCommand` if you want to register your own presets.

## Testing

The published tests under `tests/Bootstrap/` show each piece in isolation and end to end:

- `SkeletonGeneratorTest` — generation completeness, name + namespace rewriting, and the overwrite guard.
- `PresetRegistryTest` / `GenerateEnvStepTest` — preset resolution and the env transformation.
- `NewCommandTest` — the command produces a runnable project and rejects an unknown preset.
- `GeneratedPingTest` — the headline acceptance: it generates a project, autoloads its `App\` classes from the temp directory, and dispatches a real `GET /ping` through the pipeline, asserting `200` and the health payload.

## Limitations

- The command **generates**; it does not run `composer install`, migrations, or a server — those are printed as next steps (they need the network and a chosen runtime).
- ORM / queue presets currently steer the generated `.env` and the printed guidance; wiring the chosen bridge package into `config/configurations.php` automatically is a follow-up. The generated project is runnable for every preset.
- `composer create-project univeros/skeleton` (the one-liner alternative to `bin/altair new`) requires the skeleton to be published as a standalone package — that ships with the package split (see the publishing work tracked separately).
- Namespace rewriting is a textual transform over the template's `App\` token; it is reliable for the shipped template but is not a general-purpose namespace refactorer.
