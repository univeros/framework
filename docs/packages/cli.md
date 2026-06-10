# Cli

> An attribute-driven CLI runtime layered over **Symfony Console**: you write a `final` invokable class, decorate its parameters with `#[Argument]` / `#[Option]`, and the framework turns it into a fully wired console command resolved through the container.

**Composer:** `univeros/cli`
**Namespace:** `Altair\Cli`

## Introduction

Every framework needs a command line. Most of them make you extend a base `Command` class, override `configure()` to register each argument and option by hand, override `execute()` to pull those values back out of an `InputInterface`, cast the strings yourself, and remember to return an exit code. That is a lot of ceremony for "take an email and a role, create a user".

This package removes the ceremony. You write one `final` class with an `__invoke()` method, you tag its parameters with attributes, and `Altair\Cli` reflects the rest. The parameter's native type *is* the argument's type: declare `int $count` and you get integer coercion plus a clean error message when someone passes `--count=banana`. Declare a backed enum and only its valid cases are accepted. The class is constructed through `Altair\Container`, so your command's collaborators (repositories, services, configuration) are autowired the same way they are everywhere else in the framework.

The runtime is a thin shell around Symfony Console, not a replacement for it. `Application` extends `Symfony\Component\Console\Application`, so you keep Symfony's help output, `--no-interaction`, `list`, shell completion, and the entire `CommandTester` testing harness. What the package adds is the bridge: a way to author commands as plain invokables and have them register, autowire, and bind their input without you touching Symfony's `InputDefinition` API directly.

This is the substrate the rest of the framework's tooling sits on. `bin/altair manifest:generate`, `bin/altair spec:scaffold`, `bin/altair db:migrate`, `bin/altair doctor`, and the MCP server commands are all plain `#[Command]` classes discovered through this package. When you add your own application commands, you write them the same way.

## Installation

Standalone:

```bash
composer require univeros/cli
```

That pulls in `symfony/console ^7.0`, `univeros/container ^2.0`, and `univeros/configuration ^2.0`. No PHP extensions beyond what core PHP 8.3 already requires.

If you are installing the full framework, `composer require univeros/framework` already bundles it, and you already have the `bin/altair` entry point on disk.

## Quick start

A command is a `final` class carrying `#[Command]`, with an `__invoke()` whose parameters carry `#[Argument]` / `#[Option]`. Save this as `src/Cli/GreetCommand.php`:

```php
<?php

declare(strict_types=1);

namespace App\Cli;

use Altair\Cli\Attribute\Argument;
use Altair\Cli\Attribute\Command;
use Altair\Cli\Attribute\Option;

#[Command(name: 'greet', description: 'Print a friendly greeting.')]
final readonly class GreetCommand
{
    public function __invoke(
        #[Argument(description: 'Who to greet.')]
        string $name,
        #[Option(description: 'Repeat the greeting N times.', short: 'c')]
        int $count = 1,
        #[Option(description: 'Shout in upper case.')]
        bool $loud = false,
    ): int {
        $message = "Hello, {$name}!";
        if ($loud) {
            $message = strtoupper($message);
        }

        for ($i = 0; $i < $count; $i++) {
            echo $message, PHP_EOL;
        }

        return 0;
    }
}
```

Point the binary at the directory that holds it and run it:

```bash
ALTAIR_CLI_PATHS="$(pwd)/src/Cli" bin/altair greet World --count=2 --loud
# HELLO, WORLD!
# HELLO, WORLD!
```

Symfony Console gives you `--help` for free, derived entirely from your attributes:

```bash
ALTAIR_CLI_PATHS="$(pwd)/src/Cli" bin/altair greet --help
```

No `configure()`, no `execute()`, no manual `InputArgument`/`InputOption` registration. The `string $name` became a required argument, `int $count = 1` became an optional value option that only accepts integers, and `bool $loud = false` became a value-less flag.

## Concepts

The package has a small number of moving parts, and they line up one-to-one with the namespaces under `Altair\Cli\*`:

- **Attribute-driven invokable commands.** A command is any class with the `#[Command]` attribute (`Altair\Cli\Attribute\Command`) and an `__invoke()` method. There is no base class to extend and no interface to implement. `Command` carries `name`, `description`, `aliases`, `hidden`, and `help`; `#[Argument]` and `#[Option]` describe the `__invoke()` parameters.

- **`Application` extends Symfony Console.** `Altair\Cli\Application` is a `Symfony\Component\Console\Application` subclass. Its one addition is `discover(iterable $commandClasses)`, which wraps each discovered class in an `AltairCommand` and registers it. Everything else (argument parsing, help rendering, exit-code handling) is Symfony's.

- **The `AltairCommand` bridge.** `AltairCommand` is the adapter between your invokable and Symfony's `Command` surface. One `AltairCommand` wraps exactly one of your classes. In `configure()` it reflects `__invoke()` and registers each parameter as an argument or option; in `execute()` it pulls each value back out of the `InputInterface`, coerces it to the parameter's declared type, constructs your class via the container, and calls `__invoke()`.

- **Container autowiring of command constructors.** When a command runs, `AltairCommand::execute()` calls `$container->make($commandClass)`, so your command's *constructor* dependencies are autowired by `Altair\Container`, exactly like any other service. The fixture command `CreateUserIntegrationCommand` takes a `SpyUserRepository` in its constructor and never news it up; the container provides it. Your `__invoke()` parameters are the CLI inputs; your `__construct()` parameters are your collaborators.

- **Discovery via attribute scan.** `Discovery\AttributeCommandDiscoverer` (which implements `Contracts\CommandLocatorInterface`) walks the registered directories, tokenizes every `.php` file to read its namespace and class declarations *without including the file*, then reflects on each candidate to check for the `#[Command]` attribute. Abstract classes, interfaces, and traits are skipped. Each class is yielded once, even if a path is listed twice.

The shape that ties them together at boot:

```
ALTAIR_CLI_PATHS + built-in Cli/ dirs
        │
        ▼
AttributeCommandDiscoverer::scan() ──► [class-string, ...]
        │
        ▼
Application::discover() ──► AltairCommand (one per class)
        │
   Application::run()
        │
        ▼
AltairCommand::execute() ──► Container::make() ──► __invoke(...$coercedArgs)
```

## Usage

### Writing a command

Three rules, all enforced at registration time so mistakes surface loudly rather than silently misbehaving:

1. The class carries `#[Command(name: ..., description: ...)]`. A class without it is ignored by discovery, and `AltairCommand` throws `InvalidCommandException` if you wire one up directly.
2. The class defines `__invoke()`. Missing it is an `InvalidCommandException`.
3. Every `__invoke()` parameter carries either `#[Argument]` or `#[Option]`. A naked parameter throws `InvalidCommandException`; there is no implicit positional binding.

`__invoke()` returns `int` or `void`. Return `0` (or nothing) for success and a non-zero int to signal failure; the value becomes the process exit code. Returning anything else throws `InvalidCommandException`.

Here is a realistic command (the one the package's own integration test exercises) showing constructor autowiring, an argument, a nullable option, an enum option, a short alias, and a boolean flag:

```php
<?php

declare(strict_types=1);

namespace App\Cli;

use Altair\Cli\Attribute\Argument;
use Altair\Cli\Attribute\Command;
use Altair\Cli\Attribute\Option;
use App\User\Role;
use App\User\UserRepository;

#[Command(
    name: 'users:create',
    description: 'Create a new user account',
    aliases: ['users:add'],
    help: 'Detailed help block shown by `users:create --help`.',
)]
final readonly class CreateUserCommand
{
    public function __construct(
        private UserRepository $repository,   // autowired by the container
    ) {}

    public function __invoke(
        #[Argument(description: 'The user email')]
        string $email,
        #[Option(description: 'Initial password (random if omitted)', short: 'p')]
        ?string $password = null,
        #[Option(description: 'User role', short: 'r')]
        Role $role = Role::Member,
        #[Option(description: 'Skip welcome email')]
        bool $silent = false,
    ): int {
        $this->repository->create($email, $password, $role, $silent);

        return 0;
    }
}
```

Invoked as:

```bash
bin/altair users:create jane@example.com --password=s3cret --role=admin --silent
bin/altair users:add  jane@example.com -p s3cret -r admin        # alias + shorts
```

### How arguments and options are bound

The two binders translate reflection into Symfony's input model:

- **`#[Argument]`** (positional). `Binding\ArgumentBinder` makes it `InputArgument::REQUIRED` when the parameter has no default, `InputArgument::OPTIONAL` when it does. An `array`-typed parameter becomes a variadic (`IS_ARRAY`) argument. The public name defaults to the parameter name; override it with `#[Argument(name: 'custom')]`.

- **`#[Option]`** (named, `--flag`). `Binding\OptionBinder` makes a `bool` parameter a value-less flag (`VALUE_NONE`, presence means `true`), and everything else a `VALUE_REQUIRED` option. An `array`-typed option becomes repeatable (`VALUE_IS_ARRAY`). The public name defaults to the parameter name **kebab-cased** (`$dryRun` becomes `--dry-run`), and `#[Option(short: 'p')]` adds a single-character alias. Override the long name with `#[Option(name: 'custom')]`.

### How values are coerced

Symfony Console hands you strings (and `true` for value-less flags). `Binding\ValueCoercer` casts each raw value to the parameter's declared native type before `__invoke()` is called, and throws `ValueCoercionException` (surfaced as Symfony's own `InvalidArgumentException`, so the user sees a clean console error) when a value does not fit:

| Declared type | Accepted input | Notes |
|---|---|---|
| `string` | any scalar | cast with `(string)` |
| `int` | `-?\d+` strings | rejects `1.5`, `banana` |
| `float` | numeric strings | accepts `1`, `1.5`, `1e3` |
| `bool` | `1/true/yes/on/y` → true; `0/false/no/off/n/''` → false | case-insensitive |
| `array` | repeated option values, or a single comma-separated string | `--tag=a,b` and `--tag=a --tag=b` both yield `['a', 'b']` |
| `DateTimeImmutable` | any ISO-8601 string | parsed via the `DateTimeImmutable` constructor |
| backed enum | a valid case value | invalid cases throw with a "not a valid case of enum" message |

A nullable parameter (`?string $password = null`) passes `null` straight through when the option is absent; a non-nullable parameter that receives `null` throws. Untyped parameters are passed through unchanged.

### Registering a command's directory

The framework's CLI binary, `bin/altair`, builds its list of command paths at startup from two sources:

1. **Built-in package directories.** It adds each framework sub-package's `Cli/` directory that exists on disk: `src/Altair/AgentSpec/Cli`, `src/Altair/Doctor/Cli`, `src/Altair/Mcp/Cli`, `src/Altair/Messaging/Cli`, `src/Altair/Persistence/Cli`, and `src/Altair/Scaffold/Cli`. This is why installing the full framework gives you `manifest:*`, `spec:*`, `db:*`, and the rest with zero configuration.

2. **The `ALTAIR_CLI_PATHS` environment variable.** A `PATH_SEPARATOR`-delimited list of additional directories to scan (the legacy singular `ALTAIR_CLI_PATH` is also honored). This is how *your* application's commands get picked up:

```bash
# one directory
export ALTAIR_CLI_PATHS="/abs/path/to/app/src/Cli"

# several, colon-separated on Unix
export ALTAIR_CLI_PATHS="/app/src/Cli:/app/modules/billing/Cli"

bin/altair greet World
```

Each entry that is not an existing directory is silently skipped, so a stale path in the env var will not crash the binary. Discovery is recursive; nested sub-directories are scanned too.

## Configuration

`Configuration\CliConfiguration` is the single wiring point, and it implements the framework's `ConfigurationInterface`. Construct it with the list of paths to scan (plus optional application name and version) and call `apply()` on a container. It does three things: shares the `AttributeCommandDiscoverer` so the scan result is reused, aliases `CommandLocatorInterface` to that discoverer, and delegates `Application` construction so the app auto-discovers commands the first time it is resolved.

`bin/altair` does exactly this for you, but if you embed the CLI in your own bootstrap (a custom binary, a test, a long-running worker that shells out to commands), wire it by hand:

```php
use Altair\Cli\Application;
use Altair\Cli\Configuration\CliConfiguration;
use Altair\Container\Container;

$container = new Container();

(new CliConfiguration(
    paths: [__DIR__ . '/src/Cli'],
    name: 'My App',
    version: '1.0.0',
))->apply($container);

$application = $container->make(Application::class);

exit($application->run());
```

`name` and `version` default to `Application::DEFAULT_NAME` (`'Altair'`) and `Application::DEFAULT_VERSION` (`'2.x-dev'`), which is what you see in `bin/altair --version`. Because command classes are resolved through the same `$container`, anything you have bound (repositories, configuration, env-driven services) is available to command constructors. See [container.md](./container.md) for the binding API and [configuration.md](./configuration.md) for the `ConfigurationInterface` contract.

## Testing

You do not need the `bin/altair` binary to test a command. Wrap your class in an `AltairCommand` against a container, hand it to Symfony's `CommandTester`, and assert on the exit code and your collaborators. The package's own suite under `tests/Cli/` is the canonical reference:

```php
use Altair\Cli\AltairCommand;
use Altair\Container\Container;
use Symfony\Component\Console\Tester\CommandTester;

$container = new Container();
$container->share($spyRepository);                 // autowired into the command's constructor

$command = new AltairCommand(CreateUserCommand::class, $container);
$tester = new CommandTester($command);

$exitCode = $tester->execute([
    'email'      => 'jane@example.com',
    '--password' => 's3cret',
    '--role'     => 'admin',
    '--silent'   => true,
]);

self::assertSame(0, $exitCode);
```

Tests worth reading before you write your own:

- `tests/Cli/IntegrationTest.php`: the end-to-end story: container autowiring, default fall-through, enum coercion producing a clean Symfony error, `CliConfiguration` auto-discovery, and help output assembled from attributes.
- `tests/Cli/Binding/ArgumentBinderTest.php` / `OptionBinderTest.php`: exactly how parameters map to required/optional/array/flag modes and how names are derived.
- `tests/Cli/Binding/ValueCoercerTest.php`: every coercion rule in the table above, including the failure messages.
- `tests/Cli/Discovery/AttributeCommandDiscovererTest.php`: what the scanner does and does not pick up (see its `fixtures/` directory, including `NotACommand.php`).

## Related packages

- [container.md](./container.md): the DI container `CliConfiguration` writes into and `AltairCommand` uses (`make()`) to construct your command. Command constructor dependencies are autowired here.
- [configuration.md](./configuration.md): the `ConfigurationInterface` contract `CliConfiguration` implements.
- [agent-spec.md](./agent-spec.md): exposes `manifest:generate` / `manifest:show` as `#[Command]` invokables through this runtime.
- [scaffold.md](./scaffold.md): exposes `spec:scaffold`, `spec:lint`, `journal:*`, and the SDK/OpenAPI emitters as commands; `ScaffoldCommand` is the canonical real-world example of this package's attributes.
- [doctor.md](./doctor.md): its diagnostic checks are surfaced as `#[Command]` classes discovered from `src/Altair/Doctor/Cli`.
- [mcp.md](./mcp.md): the MCP server commands plug into the same attribute-driven discovery.

## Limitations

- **Console input only.** Coercion covers scalars, `array`, `DateTimeImmutable`, and backed enums. Arbitrary value objects, union types, and intersection types are not coerced: a `__invoke()` parameter typed as something the `ValueCoercer` does not recognize throws `ValueCoercionException`. Resolve such collaborators through the *constructor* (where the container autowires them) instead of through `__invoke()`.
- **No interactive prompting layer.** The package binds input and dispatches; it does not add a question/prompt helper on top of Symfony. Use Symfony Console's `QuestionHelper` directly if you need it, but a command authored here receives plain `__invoke()` parameters, not the `InputInterface`/`OutputInterface` pair, so interactive prompts mean reaching for Symfony's API around the framework's bridge.
- **Discovery is filesystem-based.** `AttributeCommandDiscoverer` scans directories you point it at; it does not read PSR-4 maps or composer metadata. Commands shipped inside a vendored package are not auto-discovered unless that package's `Cli/` directory is a built-in path or you add it to `ALTAIR_CLI_PATHS`.
- **One `#[Command]` per class.** Each `AltairCommand` wraps exactly one invokable. A class that needs to expose several sub-commands should be several classes.
- **No global flags of its own.** Beyond what Symfony Console provides (`--help`, `--quiet`, `--no-interaction`, `--verbose`, `--version`), the package adds no framework-wide options. Cross-cutting behavior belongs in your command constructors via the container.
