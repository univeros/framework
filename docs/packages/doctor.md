# Doctor

> A health-check runner that probes your project (PHP version, extensions, container wiring, code style, tests, database, spec drift) and reports back in two shapes: scannable text for you, and deterministic JSON an AI agent can act on without guessing.

**Composer:** `univeros/doctor`
**Namespace:** `Altair\Doctor`

## Introduction

When an agent (or a human) sits down in a fresh checkout and something is subtly wrong (the wrong PHP on PATH, a missing `ext-redis`, a Configuration that never got applied, stale `vendor/`) the failure usually surfaces three steps later as an inscrutable boot error. By then the agent has burned context chasing a symptom instead of the cause. Doctor exists to front-load that diagnosis: run one command, get a list of everything that is wrong *and* the exact next action for each problem.

That last part is the difference between Doctor and a plain "lint everything" script. Every failing check carries an `agent_action` (a structured "do this next" block: `run_command`, `edit_file`, `install_dep`) alongside human-readable detail. An agent reads the JSON, sees `{"type": "run_command", "command": "composer install"}`, and runs it. No prose parsing, no heuristics.

The package is deliberately small and contract-first. A check is a side-effect-free probe (`CheckInterface`); a check that can fix itself opts into `FixableCheckInterface`; sub-processes go through `ProcessRunnerInterface` so process-backed checks stay unit-testable; output goes through `ReportRendererInterface` so `human` and `json` are just two registered renderers. Everything else (the default check set, the env-derived requirements, the host-app hooks) is wiring you can replace.

What Doctor deliberately does *not* do: it does not mutate anything in `run()` (probing is read-only), it does not perform destructive fixes (`fix()` is contractually safe and non-destructive), and it does not invent its own quality tooling: `cs_clean` shells out to `composer cs`, `phpstan_clean` to `composer stan`, and so on. Doctor is the orchestrator and the reporter, not a re-implementation of the tools it drives.

## Installation

Standalone:

```bash
composer require --dev univeros/doctor
```

You will usually want this as a dev dependency: it diagnoses your *checkout*, not your runtime. If you install the full framework, `composer require univeros/framework` already bundles it.

The package depends only on `univeros/cli` (the `doctor` command plugs into attribute-driven command discovery), `univeros/configuration` (the `DoctorConfiguration` wiring), and `univeros/container` (binding resolution). No PHP extensions beyond core PHP 8.3.

## Quick start

Run every registered check and print a scannable report:

```bash
bin/altair doctor
```

The output is one line per check, with the remediation indented directly beneath any non-ok result:

```
[ok   ] php_version — PHP 8.3.10 satisfies >=8.3
[ok   ] extensions_loaded — All required extensions loaded: mbstring, pdo.
[warn ] composer_deps — Composer dependencies are out of date or composer.lock has drifted.
        fix: Run `composer install`.
        $ composer install
[ok   ] cs_clean — Code style is clean.

WARN — 14 checks in 8421ms
```

Run only a named subset (comma-separated) when you want a fast feedback loop, skipping the slow suite and keeping the cheap probes:

```bash
bin/altair doctor --only=php_version,extensions_loaded,cs_clean
```

Run everything *except* the slow checks (the PHPUnit suite is the usual one to drop):

```bash
bin/altair doctor --skip=tests_passing
```

Emit machine-readable JSON (this is what an agent or a CI step parses):

```bash
bin/altair doctor --format=json
```

```json
{
    "status": "warn",
    "duration_ms": 8421,
    "checks": [
        {
            "name": "composer_deps",
            "status": "warn",
            "detail": "Composer dependencies are out of date or composer.lock has drifted.",
            "fix": "Run `composer install`.",
            "agent_action": { "type": "run_command", "command": "composer install" }
        }
    ]
}
```

Attempt safe auto-fixes for any check that supports one, then re-report the post-fix state:

```bash
bin/altair doctor --fix
```

The process exit code is the worst status observed: `0` for all-ok (or skipped), `1` if any check warned, `2` if any errored. That makes `bin/altair doctor` a drop-in CI gate.

> **Host-application boot is required for the host-aware checks.** `bin/altair` only wires CLI discovery (`CliConfiguration`); it does **not** apply `DoctorConfiguration` on your behalf. The default check set (the env-derived `php_version`/`extensions_loaded`, the process-backed checks, and the `container_boots` / `container_resolves` / `database_reachable` hooks) is registered when *your* entry point applies `DoctorConfiguration`. A typical host entry looks like:
>
> ```php
> #!/usr/bin/env php
> <?php
> require __DIR__ . '/../vendor/autoload.php';
>
> use Altair\Cli\Application;
> use Altair\Cli\Configuration\CliConfiguration;
> use Altair\Container\Container;
> use Altair\Doctor\Configuration\DoctorConfiguration;
>
> $container = new Container();
> (new CliConfiguration([__DIR__ . '/../app/Cli']))->apply($container);
> (new DoctorConfiguration(
>     projectRoot: __DIR__ . '/..',
>     appBooter: static fn() => require __DIR__ . '/bootstrap.php',
>     criticalBindings: [\Psr\Http\Server\MiddlewareInterface::class],
> ))->apply($container);
>
> exit($container->make(Application::class)->run());
> ```

## Concepts

**Checks are side-effect-free.** `CheckInterface::run()` is a read-only probe: it inspects PHP, reads `composer.json`, or shells out to a *read-only* sub-command (`composer install --dry-run`, `db:migrate:status`, `git diff --exit-code`). It must never mutate the project. Any remediation lives behind a separate method:

```php
interface CheckInterface
{
    public function name(): string;       // stable id: 'php_version', used by --only/--skip and dependsOn()
    public function dependsOn(): array;    // names of checks that must pass first
    public function run(): CheckResult;
}
```

**Fixes are opt-in and contractually safe.** A check that can remediate itself implements `FixableCheckInterface`, and `fix()` only runs under `--fix`:

```php
interface FixableCheckInterface extends CheckInterface
{
    public function fix(): bool;            // never destructive: no deletes, downgrades, force-pushes
}
```

When you pass `--fix`, the runner calls `fix()` on any non-ok fixable check, then re-runs `run()` so the report reflects the *post-fix* state. `composer_deps`, `cs_clean`, `migrations_pending`, and `manifests_current` are fixable; `phpstan_clean`, `tests_passing`, `database_reachable`, and the spec checks are not, because a type error or a failing test needs a root-cause edit, not a mechanical re-run.

**Dependency gating turns cascades into skips.** Each check declares `dependsOn()`. When a prerequisite errored or was skipped, the runner reports the dependent as `skipped` rather than running it, since there is no point running the PHPUnit suite when `vendor/` is stale, or probing migrations when the database is unreachable. This keeps a single root failure from producing a wall of downstream noise.

**Skipped is not a false pass.** The host-aware checks (`container_boots`, `container_resolves`, `database_reachable`, `determinism_check`) report `skipped` when their host-supplied hook is absent, never `ok`. A library-only checkout with no database simply skips `database_reachable`; it does not claim the database is fine. `skipped` contributes `0` to the exit code, exactly like `ok`, but is visibly distinct in the report.

**The JSON report is deterministic.** `Report::toArray()` and `CheckResult::toArray()` emit a fixed key order, omit absent optional fields (no `null`s, no stray keys), and carry no timestamps. The single varying field is `duration_ms`, the one timing value the framework's determinism standard permits. Two runs with the same outcomes produce byte-identical JSON apart from that field, which is what makes Doctor safe to diff in CI and stable for agents that cache by content hash.

**`ProcessRunnerInterface` keeps process-backed checks unit-testable.** Every check that shells out takes a `ProcessRunnerInterface` rather than calling `proc_open()` directly. In production that is `ShellProcessRunner` (argv form, no shell, no injection surface); in tests it is a fake that scripts results per command. The check logic gets exercised without ever spawning `composer`.

## Usage

### Running programmatically

`Doctor::run()` is the entry point; the CLI command and the MCP tool are both thin wrappers over it:

```php
use Altair\Doctor\Doctor;

/** @var Doctor $doctor */          // resolve from the Container after DoctorConfiguration::apply()
$doctor = $container->make(Doctor::class);

$report = $doctor->run(
    only: ['php_version', 'extensions_loaded'],   // empty = all checks
    skip: ['tests_passing'],
    fix: false,
);
```

The signature is:

```php
public function run(array $only = [], array $skip = [], bool $fix = false): Report
```

### Reading the report

A `Report` carries every `CheckResult` plus the run duration, and exposes the rolled-up status and exit code:

```php
$report->status();      // CheckStatus enum: worst observed (Ok | Warn | Error | Skipped)
$report->exitCode();    // 0 ok/skipped, 1 warn, 2 error
$report->checks;        // list<CheckResult>

$data = $report->toArray();
// ['status' => 'warn', 'duration_ms' => 8421, 'checks' => [ ... ]]

foreach ($report->checks as $result) {
    $result->name;          // 'composer_deps'
    $result->status;        // CheckStatus::Warn
    $result->detail;        // human-readable line
    $result->fix;           // ?string — the remediation hint
    $result->agentAction;   // ?AgentAction — the structured next action
    $result->source;        // ?string — production file the failure maps to, when known
}
```

`CheckResult` is built through named constructors (`CheckResult::ok()`, `::warn()`, `::error()`, `::skipped()`), so the optional remediation fields only ever appear on results that have a remediation.

### Branching on the structured action

The `AgentAction` block is what lets an agent act without parsing prose. It is one of three shapes:

```php
use Altair\Doctor\Result\AgentAction;

AgentAction::runCommand('composer install');               // {"type":"run_command","command":"composer install"}
AgentAction::editFile('phpstan.neon.dist', 'raise level'); // {"type":"edit_file","file":"...","hint":"..."}
AgentAction::installDep('ext-redis');                       // {"type":"install_dep","package":"ext-redis"}
```

An orchestrator reads `agent_action.type` and dispatches to the matching tool (run the command, open the file, install the dependency), then re-runs `doctor` to confirm the fix took.

### Writing and registering a custom check

A check is one small class. Here is one that verifies a `.env` file exists: a read-only probe with an `edit_file` action when it is missing:

```php
<?php

declare(strict_types=1);

namespace App\Doctor;

use Altair\Doctor\Contracts\CheckInterface;
use Altair\Doctor\Result\AgentAction;
use Altair\Doctor\Result\CheckResult;
use Override;

final readonly class EnvFilePresentCheck implements CheckInterface
{
    public function __construct(private string $projectRoot) {}

    #[Override]
    public function name(): string
    {
        return 'env_file_present';
    }

    #[Override]
    public function dependsOn(): array
    {
        return [];
    }

    #[Override]
    public function run(): CheckResult
    {
        if (is_file($this->projectRoot . '/.env')) {
            return CheckResult::ok($this->name(), '.env is present.');
        }

        return CheckResult::error(
            $this->name(),
            'No .env file found at the project root.',
            'Copy .env.example to .env and fill in the required values.',
            AgentAction::editFile('.env', 'Create from .env.example and set DB_* and APP_KEY.'),
        );
    }
}
```

Register it on the `CheckRegistry`. Order matters: checks run top-to-bottom, and `dependsOn()` references resolve against checks that already ran, so hosts typically `add()` their checks via a Container `prepare` hook after `DoctorConfiguration` has populated the default set:

```php
use Altair\Container\Container;
use Altair\Doctor\CheckRegistry;
use App\Doctor\EnvFilePresentCheck;

$container->prepare(
    CheckRegistry::class,
    static fn(CheckRegistry $registry) => $registry->add(new EnvFilePresentCheck($projectRoot)),
);
```

### Host-app hooks

Three checks come inert until you hand them a host-specific hook through `DoctorConfiguration`. Without the hook they report `skipped`, never a false pass:

| Check | Hook | What it verifies |
|---|---|---|
| `container_boots` | `appBooter: Closure(): mixed` | The application Container constructs from scratch without throwing, which is the most common "agent got stuck" failure mode. |
| `container_resolves` | `criticalBindings: list<class-string>` | Each declared PSR-11 id actually resolves (boot succeeding does not guarantee every contract is wired). |
| `database_reachable` | `databaseProbe: Closure(): bool` | The DB is reachable. A typical probe is `static fn() => $em->getConnection()->isConnected()`. |

## Configuration

`DoctorConfiguration` wires the runner, the default check set, the `ShellProcessRunner`, and the `RendererRegistry` into the Container in one `apply()` call:

```php
use Altair\Doctor\Configuration\DoctorConfiguration;

(new DoctorConfiguration(
    projectRoot: __DIR__ . '/..',                       // defaults to getcwd()
    appBooter: static fn() => require __DIR__ . '/boot.php',
    criticalBindings: [MiddlewareInterface::class, EntityManagerInterface::class],
    databaseProbe: static fn(): bool => $em->getConnection()->isConnected(),
))->apply($container);
```

The PHP floor and the required `ext-*` list are **not** hand-configured; they are read from your project's `composer.json` `require` block, so `php_version` and `extensions_loaded` always reflect what your project itself declares:

- The `php` constraint (e.g. `">=8.3"`) is parsed down to its version floor (`8.3`) and compared against the running runtime by `PhpVersionCheck`.
- Every `ext-*` requirement (e.g. `ext-redis`, `ext-pdo`) becomes an entry in the `extensions_loaded` probe, sorted for determinism.
- When `composer.json` is absent or unreadable, the floor falls back to the running PHP's `major.minor` and the extension list is empty; the checks degrade gracefully rather than erroring.

### The default check set

`DoctorConfiguration` registers these checks, in this order (the order defines `dependsOn()` resolution):

| Check name | What it probes | Fixable? |
|---|---|---|
| `php_version` | Runtime PHP satisfies the `composer.json` floor. | no |
| `extensions_loaded` | Every required `ext-*` is loaded. | no |
| `composer_deps` | `vendor/` is current with `composer.lock` (`composer install --dry-run`). | `composer install` |
| `container_boots` | Host Container constructs without errors (needs `appBooter`). | no |
| `container_resolves` | Critical bindings resolve (needs `criticalBindings`). Depends on `container_boots`. | no |
| `database_reachable` | DB is reachable (needs `databaseProbe`). | no |
| `migrations_pending` | No unapplied migrations (`db:migrate:status`). Depends on `database_reachable`. | `db:migrate` |
| `spec_drift` | Scaffolded files still match their YAML specs (`spec:lint`). | no |
| `openapi_valid` | Specs emit a well-formed OpenAPI document (`spec:emit-openapi`). | no |
| `manifests_current` | `.agent/` manifests match current source (`manifest:diff`). | `manifest:generate` |
| `cs_clean` | Code style is clean (`composer cs`). | `composer cs:fix` |
| `phpstan_clean` | Static analysis is clean (`composer stan`). | no |
| `tests_passing` | PHPUnit exits 0. Depends on `composer_deps`. | no |
| `determinism_check` | Generators are byte-stable across regeneration (needs `generators` + `paths`). | no |

### Output formats

`RendererRegistry::default()` ships `human` and `json`. To add your own format, bind a populated registry before bootstrapping:

```php
use Altair\Doctor\Output\HumanRenderer;
use Altair\Doctor\Output\JsonRenderer;
use Altair\Doctor\Output\RendererRegistry;

$container->delegate(
    RendererRegistry::class,
    static fn(): RendererRegistry => new RendererRegistry([
        'human'    => new HumanRenderer(),
        'json'     => new JsonRenderer(),
        'markdown' => new App\Doctor\MarkdownRenderer(),
    ]),
);
```

An unknown `--format` raises a `DoctorException` listing the formats that are available.

## Testing

The published tests under `tests/Doctor/` double as worked examples of every extension point:

- [tests/Doctor/DoctorTest.php](https://github.com/univeros/framework/blob/master/tests/Doctor/DoctorTest.php): the runner: `--only`/`--skip` filtering, dependency-skip behaviour, `--fix` re-run.
- [tests/Doctor/Check/PureChecksTest.php](https://github.com/univeros/framework/blob/master/tests/Doctor/Check/PureChecksTest.php): `php_version`, `extensions_loaded` (using the injectable probe).
- [tests/Doctor/Check/ProcessChecksTest.php](https://github.com/univeros/framework/blob/master/tests/Doctor/Check/ProcessChecksTest.php): the process-backed checks against the fake runner.
- [tests/Doctor/Check/HostAppChecksTest.php](https://github.com/univeros/framework/blob/master/tests/Doctor/Check/HostAppChecksTest.php): the host-hook checks, including the `skipped`-when-absent path.
- [tests/Doctor/Output/RenderersTest.php](https://github.com/univeros/framework/blob/master/tests/Doctor/Output/RenderersTest.php): human + JSON rendering, determinism of the JSON projection.
- [tests/Doctor/Result/ResultObjectsTest.php](https://github.com/univeros/framework/blob/master/tests/Doctor/Result/ResultObjectsTest.php): `CheckResult`/`Report`/`AgentAction` shape and `toArray()`.
- [tests/Doctor/Configuration/DoctorConfigurationTest.php](https://github.com/univeros/framework/blob/master/tests/Doctor/Configuration/DoctorConfigurationTest.php): env-derived requirements + Container wiring.

The key testing tool is the in-memory `FakeProcessRunner` ([tests/Doctor/Support/FakeProcessRunner.php](https://github.com/univeros/framework/blob/master/tests/Doctor/Support/FakeProcessRunner.php)): you script a result per command and assert on the calls made, so a process-backed check is exercised end-to-end without ever spawning a subprocess.

```php
use Altair\Tests\Doctor\Support\FakeProcessRunner;
use Altair\Doctor\Check\CsCleanCheck;
use Altair\Doctor\Process\ProcessResult;
use Altair\Doctor\Result\CheckStatus;

$runner = new FakeProcessRunner();
$runner->on(['composer', 'cs'], new ProcessResult(1));   // simulate a style violation

$result = (new CsCleanCheck($runner, '/project'))->run();

self::assertSame(CheckStatus::Warn, $result->status);
```

When you add a new check, mirror this: inject the dependency that touches the outside world (a `Closure` probe or the `ProcessRunnerInterface`), script it in the test, and assert on the resulting `CheckStatus`. No new check should require a real PHP, a real `composer`, or a real database to test.

## Extending

The two natural extension points are the check set and the renderer set.

**A new check** implements `CheckInterface` (or `FixableCheckInterface` for self-remediation) and is `add()`-ed to the `CheckRegistry`, as shown in [Usage](#writing-and-registering-a-custom-check) above. If it touches the filesystem or a sub-process, inject the dependency rather than calling it directly so the check stays testable. Set `dependsOn()` to the names of any checks that must pass first; the runner will skip yours if a prerequisite breaks.

**A new renderer** implements `ReportRendererInterface` and is registered in a `RendererRegistry` under its `--format` key:

```php
use Altair\Doctor\Contracts\ReportRendererInterface;
use Altair\Doctor\Result\Report;
use Override;

final readonly class MarkdownRenderer implements ReportRendererInterface
{
    #[Override]
    public function render(Report $report): string
    {
        $rows = array_map(
            static fn($c): string => \sprintf('| %s | %s | %s |', $c->name, $c->status->value, $c->detail),
            $report->checks,
        );

        return "| Check | Status | Detail |\n|---|---|---|\n" . implode("\n", $rows) . "\n";
    }
}
```

The contract requires determinism (same `Report`, byte-identical output, with `duration_ms` aside), so avoid `microtime()` and unordered iteration in your renderer.

## Related packages

- [`univeros/cli`](./cli.md): the attribute-driven CLI substrate. `DoctorCommand` is a plain invokable registered through `#[Command(name: 'doctor')]`; `--format`/`--only`/`--skip`/`--fix` are `#[Option]`s.
- [`univeros/introspection`](./introspection.md): the broader "what is this project?" tooling. Doctor answers "is this project healthy?"; introspection answers "what does it contain?".
- [`univeros/scaffold`](./scaffold.md): the spec scaffolder. `spec_drift` and `openapi_valid` drive its `spec:lint` / `spec:emit-openapi` commands; `manifests_current` and `determinism_check` guard the same generated content.
- [`univeros/mcp`](./mcp.md): the MCP server. Its `framework__doctor` tool wraps `Doctor::run()` and returns `Report::toArray()`, so an MCP-connected agent gets the same structured report the CLI emits as JSON.
- [`univeros/container`](./container.md): resolves `Doctor`, `CheckRegistry`, and the renderers; `container_resolves` probes bindings through its PSR-11 `get()`.

## Limitations

- **The host-aware checks need a host.** `container_boots`, `container_resolves`, `database_reachable`, and `determinism_check` are inert (`skipped`) until you supply their hooks via `DoctorConfiguration`. `bin/altair doctor` from a bare framework checkout, with no host entry point applying `DoctorConfiguration`, will not run the default check set; wire the Configuration in your application's entry point (see the [Quick start](#quick-start) callout).
- **Process-backed checks shell out.** `composer_deps`, `cs_clean`, `phpstan_clean`, `tests_passing`, and the spec/manifest checks invoke `composer`, `vendor/bin/phpunit`, `git`, and `bin/altair` as sub-processes. If those binaries are not on PATH (or PHP is not installed), the underlying `proc_open()` fails and the check reports a non-ok status; Doctor diagnoses tool availability rather than guaranteeing it.
- **`--fix` is intentionally conservative.** Fixes are limited to safe, mechanical operations (`composer install`, `cs:fix`, `db:migrate`, `manifest:generate`). Type errors, failing tests, and unreachable databases are reported, never auto-resolved; they need a root-cause edit a human or agent must make.
- **No parallelism.** Checks run sequentially, top-to-bottom, so the registry order is also the report order. The slow members (`tests_passing`, `phpstan_clean`) dominate wall-clock time; use `--skip` for a fast inner-loop run.
