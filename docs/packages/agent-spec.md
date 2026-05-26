# AgentSpec

> A manifest generator that turns the framework's PHP source into Markdown packets an AI coding agent can read in seconds — without opening a single source file.

**Composer:** `univeros/agent-spec`
**Namespace:** `Altair\AgentSpec`

## Introduction

AI agents that write code against this framework spend most of their context window reading source. Even with a fully typed, magic-free codebase, "what classes exist in `Altair\Http`?" still costs a hundred tokens before the agent has done anything useful. AgentSpec collapses that. You run one command, you get one Markdown file per package plus a top-level index, and any agent — Claude Code, Cursor, Codex, an internal bot — can be productive against the package after reading that single file.

The output lives under `.agent/` at the monorepo root: `.agent/packages/<slug>.md` for each sub-package, plus `.agent/MANIFEST.md` as the index. The format is Markdown on purpose. LLMs read Markdown faster than JSON, GitHub renders it for free, and human contributors can skim the same files to see what the framework advertises about itself.

Everything the generator emits is derived from PHP reflection — interfaces under `Contracts/`, concrete classes outside the skip set, `ATTRIBUTE_*` constants, tests living parallel to the source — supplemented by optional, hand-authored sidecars (`<package>/.agent/purpose.md`, `patterns.md`, `stability.md`) when prose adds value the reflection cannot. The result is deterministic: run the command twice in a row and the bytes match. That is what makes the `--check` mode a useful CI gate.

What this package deliberately does *not* do: it does not document **how to use** a package (that lives in these per-package guides under `docs/`); it does not publish a public web index; and it does not version manifests across releases. It is a per-checkout snapshot of "what does this code currently expose" — nothing more.

## Installation

Standalone:

```bash
composer require --dev univeros/agent-spec
```

You will almost always want this as a dev dependency: the manifests it emits target your *checkout*, not your runtime. If you install the full framework, `composer require univeros/framework` already bundles it.

The package depends on `univeros/cli` because the CLI commands plug into the framework's attribute-driven command discovery — see [cli.md](./cli.md) for how `manifest:generate` is wired up. No PHP extensions beyond what core PHP 8.3 already requires.

## Quick start

From the monorepo root, generate manifests for every sub-package:

```bash
bin/altair manifest:generate
```

That writes:

```
.agent/
├── MANIFEST.md                 # top-level index, one row per package
└── packages/
    ├── agent-spec.md
    ├── cache.md
    ├── …
    └── validation.md
```

Print one package's manifest to stdout without touching disk:

```bash
bin/altair manifest:show http
```

Verify the on-disk manifests still match what the source would produce — exits non-zero on drift, which is what you want in CI:

```bash
bin/altair manifest:generate --check
```

Two seconds of CPU, sixteen Markdown files, and any agent can now answer "what interfaces does `univeros/http` publish?" without round-tripping through the file system.

## Concepts

The pipeline has four roles, and they line up one-to-one with packages under `Altair\AgentSpec\*`:

- **Scanner** — `Reflection\PackageScanner` walks `src/Altair/*`, looking for directories that contain a `composer.json`. Each one becomes a `Model\PackageDescriptor` carrying the package name, root namespace, source path, optional tests path, and required-package list.
- **Generator** — `Generator\PackageManifestGenerator` composes the reflection scanners (`ContractScanner`, `ConcreteClassScanner`, `AttributeScanner`, `TestFixtureScanner`) plus the sidecar reader into a `Model\PackageManifest` value object. The descriptor goes in; a fully populated manifest comes out.
- **Renderer** — `Renderer\MarkdownPackageRenderer` turns the manifest into Markdown. The interface (`Contracts\ManifestRendererInterface`) is single-method, so emitting JSON or any other format is a swap-in.
- **Writer** — `Writer\ManifestWriter` writes the rendered string to disk (`write()`) or compares it to what is already there (`check()`).

The shape that ties them together:

```
src/Altair/<Pkg>/composer.json
        │
        ▼
PackageScanner ──► PackageDescriptor ──► PackageManifestGenerator ──► PackageManifest
                                                                          │
                                                              MarkdownPackageRenderer
                                                                          │
                                                                          ▼
                                                                ManifestWriter ──► .agent/packages/<slug>.md
```

`Generator\ManifestPipeline` is the orchestrator — it stitches all four together and drives the loop. Use it directly when you want to embed manifest generation inside another tool (a `pre-commit` hook, a custom CLI, an integration test).

## Usage

### Generating manifests for the framework

`manifest:generate` is the default workflow. From the monorepo root:

```bash
bin/altair manifest:generate
```

The command resolves four paths automatically by walking up from the current working directory until it finds a folder containing both `composer.json` and `src/Altair`:

- **`--root`** — the monorepo root used as the base for relative path display.
- **`--source`** — the source root scanned for sub-packages. Defaults to `<root>/src/Altair`.
- **`--tests`** — the tests root used to cross-reference test files. Defaults to `<root>/tests` if it exists.
- **`--output`** — where the generated files land. Defaults to `<root>/.agent`.

Override any of them when you generate manifests for a different layout (e.g. a non-monorepo consumer):

```bash
bin/altair manifest:generate \
    --root=/path/to/project \
    --source=/path/to/project/packages \
    --output=/path/to/project/docs/agent
```

Output is alphabetically sorted by package name, so a fresh run on a clean repo produces byte-identical files every time.

### Printing one manifest

When you want to inspect a single package without diffing the whole `.agent/` tree, use `manifest:show`:

```bash
bin/altair manifest:show cookie
```

The slug is the segment after `univeros/` in the composer name — `univeros/agent-spec` resolves to `agent-spec`, `univeros/http` to `http`. Nothing is written to disk; the manifest is rendered fresh from the source and printed to stdout, so you can pipe it into `less`, into a diff, or into your agent's clipboard.

### CI: enforcing manifests stay current

`--check` flips the pipeline into read-only mode. Each manifest is re-rendered in memory and compared to what is on disk. Exit code is `0` when everything matches, `1` when any file differs, with the drifted paths printed:

```bash
bin/altair manifest:generate --check
```

Wire this into the pre-commit pipeline alongside `composer cs`, `composer stan`, and `composer test`:

```yaml
# .github/workflows/ci.yml
- name: Ensure agent manifests are current
  run: bin/altair manifest:generate --check
```

If the check fails, the contributor regenerates locally (`bin/altair manifest:generate`) and re-commits. The diff in the resulting PR makes it obvious *what* changed about the framework surface.

### Hand-authoring sidecar content

Reflection can describe shape; it cannot describe intent. Three optional sidecar files under `<package>/.agent/` let you add prose the renderer will fold in verbatim:

| File | Section it populates | When to write one |
|---|---|---|
| `purpose.md` | The one-paragraph **Purpose:** line at the top | When `composer.json`'s `description` is too thin to be useful |
| `patterns.md` | A **Common patterns** section, split on lines containing only `---` | When the typical wiring of the package is non-obvious from the class list |
| `stability.md` | A **Stability** section at the bottom | When you need to flag deprecations or call out an API contract |

Each file is read with `trim()`, so trailing blank lines are harmless. `patterns.md` is split into multiple subsections on lines containing only `---` (with optional surrounding whitespace), which means you can drop several fenced code blocks separated by hr lines and they will render as discrete patterns. Sidecars are optional — packages without them simply skip those sections.

### Generating an application manifest

`Generator\ApplicationManifestGenerator` is the v1 stub for the host-application side: it scans paths you point it at, finds classes that carry one or more framework attribute markers (`Altair\Cli\Attribute\Command`, future HTTP route attributes, event handlers, jobs), and groups them by attribute short-name.

```php
use Altair\AgentSpec\Generator\ApplicationManifestGenerator;
use Altair\Cli\Attribute\Command;

$generator = new ApplicationManifestGenerator([Command::class]);

file_put_contents(
    __DIR__ . '/.agent/APPLICATION.md',
    $generator->render([__DIR__ . '/src']),
);
```

The output is grouped by attribute short-name with classes listed alphabetically under each. As richer attribute conventions land elsewhere in the framework (event subscribers, queued jobs, HTTP actions), pass them in the same list — the generator does not need to know about each individually.

## Configuration

There is no `Configuration` class to wire — the CLI commands are plain `readonly` invokables and the pipeline is constructible with new-on-default arguments. The `bin/altair` entry point auto-discovers them via the `univeros/cli` attribute scanner: it adds `src/Altair/AgentSpec/Cli` to the command path list at startup, so no opt-in is required when you install the package.

If you want to override pieces of the pipeline — for instance, to swap in a JSON renderer — wire them via the container instead of going through `bin/altair`:

```php
use Altair\AgentSpec\Generator\ManifestPipeline;
use Altair\AgentSpec\Generator\ManifestPipelineOptions;
use Altair\AgentSpec\Renderer\MarkdownPackageRenderer;
use Altair\Container\Container;

$container = new Container();
$container->define(ManifestPipeline::class, [
    ':renderer' => new MarkdownPackageRenderer(),
]);

/** @var ManifestPipeline $pipeline */
$pipeline = $container->make(ManifestPipeline::class);
$pipeline->run(new ManifestPipelineOptions(
    monorepoRoot: '/path/to/repo',
    sourceRoot: '/path/to/repo/src/Altair',
    testsRoot: '/path/to/repo/tests',
    outputRoot: '/path/to/repo/.agent',
    checkOnly: false,
));
```

See [container.md](./container.md) for the binding API.

## Testing

The published tests under `tests/AgentSpec/` are the most honest description of how each component is meant to be used:

- `tests/AgentSpec/PackageScannerTest.php` — how composer.json is parsed into a `PackageDescriptor`, and what happens when a package directory is missing one.
- `tests/AgentSpec/PackageManifestGeneratorTest.php` — the contract between descriptors and rendered manifests, including the sidecar overrides.
- `tests/AgentSpec/MarkdownPackageRendererTest.php` — golden snapshot at `tests/AgentSpec/Snapshots/sample-package.md`. Regenerate it with `composer test -- --testdox` after intentional rendering changes and diff before committing.
- `tests/AgentSpec/ManifestPipelineTest.php` — end-to-end: a temp directory tree of fixture packages, run through the full pipeline in both write and check modes.

When you add a new scanner or convention, mirror this pattern: a small fixture package under `tests/AgentSpec/Fixtures/`, a snapshot file, and a test that diffs the rendered output against the snapshot. Determinism is the whole value proposition — the test suite is what defends it.

## Extending

The two natural extension points are the scanner set and the renderer.

To plug in a new scanner — say, one that reads `@deprecated` PHPDoc tags off public methods — implement nothing new at the contract layer; just add a private dependency to `PackageManifestGenerator` and a new field on `PackageManifest`. Then teach the renderer how to emit it. Both classes are non-final on purpose so you can subclass when you cannot edit upstream.

To plug in a new renderer — e.g. a JSON sidecar emitted alongside the Markdown — implement `Contracts\ManifestRendererInterface`:

```php
use Altair\AgentSpec\Contracts\ManifestRendererInterface;
use Altair\AgentSpec\Model\PackageManifest;

final class JsonPackageRenderer implements ManifestRendererInterface
{
    public function render(PackageManifest $manifest): string
    {
        return json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
    }
}
```

Pass it into `ManifestPipeline` and you have JSON manifests alongside (or in place of) the Markdown ones. The contract requires determinism — same input, byte-identical output — so avoid `microtime()` and any unordered iteration in your renderer.

The package scanner is also pluggable behind `Contracts\PackageScannerInterface`. Override it when you want to filter packages (e.g. emit manifests only for a subset) or pull metadata from somewhere other than `composer.json`.

## Recipes

### Generate manifests before opening a PR

Add a pre-commit hook so manifests track source changes automatically:

```bash
# .git/hooks/pre-commit
#!/usr/bin/env bash
set -e
bin/altair manifest:generate
git add .agent/
```

Heavy-handed but reliable. The lighter alternative is the `--check` CI gate above, which lets you skip the local hook and require the contributor to regenerate when CI complains.

### Carry manifests to an external agent

Most agents want a single document. Concatenate the per-package manifests into one bundle when bootstrapping:

```bash
cat .agent/MANIFEST.md .agent/packages/*.md > /tmp/altair.md
# upload /tmp/altair.md or pipe into your agent's context
```

The order is deterministic (`*` glob expands alphabetically on every supported shell), so re-running this produces stable input — agents that cache by content hash will not re-process unchanged bundles.

### Author a sidecar `patterns.md`

When the inferred manifest reads like an unordered class dump, drop a `<package>/.agent/patterns.md` to show the agent the canonical wiring:

```markdown
### Build a middleware pipeline

\`\`\`php
new Relay([
    new IpAddressMiddleware(),
    new CorsMiddleware($analyzer, $responseFactory),
    new ActionMiddleware($resolver, $responseFactory),
]);
\`\`\`

---

### Short-circuit early

Middleware that fail closed (auth, IP block, CSRF) return a response built
via `ResponseFactoryInterface` rather than calling `$handler->handle()`.
```

Each fenced section separated by `---` becomes its own subsection under **Common patterns** in the rendered manifest. The renderer treats your content as verbatim Markdown, so use whatever inline formatting helps the agent the most.

## Related packages

- [cli.md](./cli.md) — the attribute-driven CLI substrate. `manifest:generate` and `manifest:show` are plain invokable classes registered through `Altair\Cli\Attribute\Command`.
- [container.md](./container.md) — wires the pipeline together when you want a non-default renderer or scanner. Both commands are constructed with new-on-default dependencies, so the container is opt-in.
- [http.md](./http.md), [cookie.md](./cookie.md), [session.md](./session.md), etc. — the packages whose manifests this tool emits. The HTTP package is the canonical example of a package that benefits from sidecars (its request-attribute conventions are central to using it correctly).

## Limitations

- Manifests describe the **shape** of a package — its interfaces, classes, attribute constants, tests — not its **behaviour**. Use these guides under `docs/packages/` for behaviour; use `.agent/` for surface.
- The application manifest generator is a v1 stub: it groups by attribute short-name only. Richer per-attribute renderers will arrive alongside the scaffolder packages.
- The renderer's table layout assumes ASCII-safe interface and method names. Multibyte characters render fine, but `|` characters in type strings are escaped to `\|` so the Markdown table stays parseable.
- There is no version pinning across framework releases: the manifest reflects the source as it exists in your checkout, period. If you need to consult a manifest for a prior tag, regenerate from that tag.
