# Tinker

> An interactive [PsySH](https://psysh.org) REPL with your DI container already in scope and a doctor-style preamble of what's wired. `bin/altair tinker` is the *dynamic* counterpart to [`univeros/introspection`](./introspection.md): where introspection answers "what is wired?" as static tables, Tinker drops you into a live shell to poke at it — `>>> $container->make(UserRepository::class)`.

**Composer:** `univeros/tinker`
**Namespace:** `Altair\Tinker`

## Introduction

Sometimes you don't want a command or a test — you want to *try something*. Resolve a service and call a method on it, check what a value object serialises to, confirm a repository returns `null` rather than throwing. Tinker gives you that: an interactive PHP shell (PsySH, the same engine Laravel Tinker uses) with history, tab-completion, and the framework's `Container` bound to `$container`.

It is deliberately a **human** tool. Agents have the static introspection commands (`--format=json`), the `framework__*` MCP tools, and the spec-driven workflow; those are read-once, deterministic, and scriptable. A REPL is none of those — it is the developer's "let me just check" surface, so it lives in its own package that only host apps wanting a REPL pull in.

The package is a thin, well-seamed wrapper. All the PsySH-touching code sits behind a single `ReplInterface`, so the command, the preamble, and the configuration are ordinary unit-tested classes; only the one line that hands control to the interactive shell is untested (it blocks on stdin by nature). PsySH itself is a **dev dependency** of the framework — it never ships into a production install of `univeros/framework`, and `bin/altair tinker` degrades to a clear "install psy/psysh" message if it is absent.

## Installation

Standalone:

```bash
composer require --dev univeros/tinker
```

The split `univeros/tinker` package requires `psy/psysh` directly, so a standalone install is ready to go. In the bundled framework, PsySH is a `require-dev` dependency: present in development, absent from `--no-dev` production installs (where `bin/altair tinker` prints an install hint and exits `2`). It depends on `univeros/cli`, `univeros/configuration`, and `univeros/container`; [`univeros/introspection`](./introspection.md) is a *suggested* dependency that enriches the startup preamble.

## Quick start

```bash
bin/altair tinker
```

```
Altair Tinker — interactive REPL. Ctrl+D to exit, `help` for PsySH commands.

In scope:
  $container    the DI container — resolve with $container->make(Foo::class)

Wired:
  bindings   42
  routes     8
  listeners  3

>>> $container->make(App\User\UserRepository::class)->count()
=> 0
>>> $container
=> Altair\Container\Container { class: ..., realised singletons: 12, tip: "resolve services with $container->make(Foo::class)" }
```

Override where history is stored for this session:

```bash
bin/altair tinker --history-file=storage/tinker_history
```

PsySH's own commands all work inside the shell — `ls` to list scope, `dump $x`, `show SomeClass`, `doc strlen`, `history`, and `help`. Press `Ctrl+D` (or type `exit`) to leave.

## Concepts

**The container is the entry point.** The one scope variable Tinker guarantees is `$container`. Everything else in your app is reachable through it: `$container->make(SomeService::class)` autowires and returns an instance, exactly as the framework does at runtime. A `ContainerCaster` keeps `$container` from dumping its entire internal graph — it renders a short summary (class, realised-singleton count, a usage tip) instead.

**The preamble tells you what you can poke at.** On startup Tinker prints a doctor-style summary: the variables in scope, and a count of the project's bindings, routes, and listeners. The counts come from the [introspection](./introspection.md) inspectors; any that are missing (introspection not installed, or its collections not shared) degrade to `—` with a one-line hint rather than failing.

**Host wiring makes it real.** The framework `Container` does not self-inject, so a bare `bin/altair tinker` puts a *fresh* container in scope — useful for autowiring experiments, but it doesn't see your app's bindings. Apply `TinkerConfiguration` from your application's bootstrap and the REPL gets your **booted** container (and accurate preamble counts). This is the same wiring contract as [`univeros/suggest`](./suggest.md) and the introspection inspectors.

**PsySH is isolated behind one seam.** `ReplInterface` has two methods — `isAvailable()` and `run()`. `PsyShellRepl` is the only class that constructs a `Psy\Shell`; `PsyConfigurationFactory` builds the `Psy\Configuration` (history, casters, startup banner) without running anything. So the command is testable with a fake REPL, and the package never hard-fails when PsySH is absent.

## Usage

### Putting your booted container in scope

`bin/altair` only wires CLI discovery, so apply `TinkerConfiguration` wherever you boot your application's container to get the real wiring into the REPL:

```php
use Altair\Tinker\Configuration\TinkerConfiguration;

(new TinkerConfiguration(
    historyFile: 'storage/tinker_history',  // optional; default .altair/tinker_history
    historySize: 1000,                       // optional; 0 = PsySH default
))->apply($container);
```

With no constructor arguments it reads `ALTAIR_TINKER_HISTORY_FILE` and `ALTAIR_TINKER_HISTORY_SIZE` from the environment, falling back to `.altair/tinker_history`. Apply `IntrospectionConfiguration` alongside it (and share your `RouteCollection` / `EventDispatcher`) for the route and listener counts.

### Adding your own scope variables

The REPL scope is a plain `array<string, mixed>` on the `ReplContext`. Bind a context with extra variables before bootstrapping:

```php
use Altair\Tinker\Repl\ReplContext;

$container->delegate(
    ReplContext::class,
    static fn(): ReplContext => (new ReplContext(scopeVariables: ['container' => $container]))
        ->withScopeVariable('db', $container->make(DatabaseProviderInterface::class))
        ->withScopeVariable('now', new DateTimeImmutable()),
);
```

Now `$db` and `$now` are available alongside `$container` in the shell.

## Configuration

| Setting | Source | Default |
|---|---|---|
| History file | `TinkerConfiguration` arg → `ALTAIR_TINKER_HISTORY_FILE` env | `.altair/tinker_history` |
| History size | `TinkerConfiguration` arg → `ALTAIR_TINKER_HISTORY_SIZE` env | `0` (PsySH default) |
| `--history-file` | CLI option (per-session override) | the configured history file |

`TinkerConfiguration::apply()` binds three things: a shared `ReplContext` carrying `['container' => $yourContainer]`, a shared `PreambleBuilder` wired to whatever introspection inspectors are bound, and `ReplInterface` → `PsyShellRepl`. PsySH update checks are disabled (a bundled dev tool should never phone home).

## Testing

Everything except the one interactive line is unit-tested — the PsySH seam keeps it that way:

- [tests/Tinker/Repl/PsyConfigurationFactoryTest.php](https://github.com/univeros/framework/blob/master/tests/Tinker/Repl/PsyConfigurationFactoryTest.php) — history file and startup banner land on the `Psy\Configuration`.
- [tests/Tinker/Repl/PsyShellReplTest.php](https://github.com/univeros/framework/blob/master/tests/Tinker/Repl/PsyShellReplTest.php) — `build()` produces a `Psy\Shell` with the scope variables set (no `run()`).
- [tests/Tinker/Repl/ContainerCasterTest.php](https://github.com/univeros/framework/blob/master/tests/Tinker/Repl/ContainerCasterTest.php) — the container summary caster.
- [tests/Tinker/Repl/ReplContextTest.php](https://github.com/univeros/framework/blob/master/tests/Tinker/Repl/ReplContextTest.php) — immutable scope-variable layering.
- [tests/Tinker/Preamble/PreambleBuilderTest.php](https://github.com/univeros/framework/blob/master/tests/Tinker/Preamble/PreambleBuilderTest.php) — the banner with and without inspectors (graceful degradation).
- [tests/Tinker/Cli/TinkerCommandTest.php](https://github.com/univeros/framework/blob/master/tests/Tinker/Cli/TinkerCommandTest.php) — with a `FakeRepl`: preamble passed through, history override, the PsySH-missing exit-2 path, fresh-container fallback.
- [tests/Tinker/Configuration/TinkerConfigurationTest.php](https://github.com/univeros/framework/blob/master/tests/Tinker/Configuration/TinkerConfigurationTest.php) — the real container is captured into scope; preamble and REPL resolve.

The single uncovered line is `PsyShellRepl::run()`'s `$shell->run()`, which blocks on stdin and cannot be unit-tested.

## Extending

`ReplInterface` is the extension point. Implement it (and bind it as `ReplInterface`) to swap PsySH for another shell, or to wrap PsySH with project-specific commands and casters via `PsyConfigurationFactory`. The command depends only on the interface, so nothing downstream changes.

## Related packages

- [`univeros/introspection`](./introspection.md) — the static "what is wired?" surface; its inspectors feed Tinker's startup preamble. Tinker is the dynamic "let me poke at it live" counterpart.
- [`univeros/suggest`](./suggest.md) — shares the same host-wiring contract (capture the booted container in a Configuration) and the "richer when wired" degradation model.
- [`univeros/container`](./container.md) — the `$container` you get in scope; `make()` is the resolution entry point.
- [`univeros/cli`](./cli.md) — `TinkerCommand` is a plain invokable registered through `#[Command(name: 'tinker')]`.

## Limitations

- **Human tool, not an agent surface.** There is intentionally no `framework__tinker` MCP tool — an interactive REPL is the opposite of the deterministic, scriptable surfaces agents use. Agents have the introspection commands and MCP tools instead.
- **PsySH is dev-only in the bundle.** `bin/altair tinker` works in development and in standalone `univeros/tinker` installs, but a `--no-dev` production install of `univeros/framework` does not ship PsySH; the command then prints an install hint and exits `2`.
- **Bare `bin/altair` gets a fresh container.** Without a host applying `TinkerConfiguration`, the `$container` in scope is a new empty instance — fine for autowiring experiments, but it does not reflect your application's bindings. Wire `TinkerConfiguration` in your bootstrap for the real thing.
- **Preamble counts depend on host wiring.** Route and listener counts need the inspectors (and their backing collections) bound; otherwise they show `—`. The binding count uses the captured container directly when introspection is installed.
