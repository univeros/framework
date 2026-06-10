# Eval

> The agent's "let me check" primitive: run a short PHP snippet in a sandboxed subprocess against the project's live container and get a structured result back. Hypothesis to validation in 200ms, no temporary scripts, no manual bootstrap.

**Composer:** `univeros/eval`
**Namespace:** `Altair\Eval`

## Introduction

When an agent (or a human) forms a hypothesis (*does `UserRepository::findByEmail` return `null` or throw when no match?*, *what does `FormatNegotiator::getContentTypeByFormat('json')` actually return?*), the cheapest way to validate it is to run a few lines of PHP. Doing that by hand means writing a temp script, knowing the autoloader path, bootstrapping the container, remembering to clean up. Agents do not do that well.

Eval collapses that loop into one command. `bin/altair eval 'return container(SomeRepo::class)->count();'` spawns a fresh PHP subprocess, requires the host's Composer autoloader, resolves the host container from `config/container.php` (the skeleton convention) or an explicit bootstrap path, runs the snippet inside a closure that captures the return value, and emits a typed JSON shape: result type and value, captured stdout, exception with class and stack trace, duration, peak memory. The subprocess runs under `disable_functions` (no `exec` / `shell_exec` / `proc_open` / `popen` / `assert`, plus the network primitives unless `--network`), `open_basedir` confinement to the project root, a hard memory cap, and a wall-clock budget that the parent enforces by SIGTERM-then-SIGKILL. Each evaluation is one-shot: a fresh subprocess, no state carried across calls.

This is the most dangerous tool in the agent's palette: eval is eval. The package is honest about what its ini-level guards can and cannot enforce. `disable_functions` blocks the function-based escape hatches (`exec`, `proc_open`, the network primitives), `open_basedir` blocks filesystem writes outside the project tree, and `memory_limit` + the parent's wall-clock kill enforce the resource budget. What ini-level sandboxing *cannot* do: block `eval()` (a language construct, not a function), kernel-level network blocking (we disable the function-based network calls, not raw sockets the OS can open), or enforce read-only database access (an `ALTAIR_EVAL_ALLOW_WRITES` env var is passed for hosts to honour cooperatively; the host's persistence config can read it). The subprocess sandbox bounds the damage even when those soft guards don't catch a malicious snippet, and the `--unsafe` flag that lifts every ini-level guard simultaneously emits a `kind=eval` event into `.altair/events.jsonl` so a "we let it write" decision leaves an audit trail.

## Installation

Standalone:

```bash
composer require --dev univeros/eval
```

You will usually want this as a dev dependency, as it executes arbitrary code at runtime, which is exactly what a production tree should never offer. If you install the full framework, `composer require univeros/framework` already bundles it.

It depends on `univeros/cli`, `univeros/configuration`, `univeros/container`, and `univeros/events` (for the `--unsafe` audit event). It needs no PHP extension beyond the standard `proc_open`.

## Quick start

The "does this return null?" check:

```bash
bin/altair eval 'return container(App\User\UserRepository::class)->findByEmail("nobody@example.com");'
```

```
✓ null
  duration=12ms  memory=8192 KB  exit=0
```

Capture an exception cleanly:

```bash
bin/altair eval 'throw new RuntimeException("boom");'
```

```
✗ RuntimeException: boom
  at /srv/app/.altair/eval/.../wrapper.php:53
  duration=0ms  memory=4096 KB  exit=1
```

Emit JSON for an agent or a CI step:

```bash
bin/altair eval 'return ["a" => 1, "b" => 2];' --format=json
```

```json
{
    "ok": true,
    "result": {
        "type": "array",
        "is_list": false,
        "count": 2,
        "value": {
            "a": { "type": "int", "value": 1 },
            "b": { "type": "int", "value": 2 }
        }
    },
    "stdout": "",
    "stderr": "",
    "exception": null,
    "duration_ms": 0,
    "memory_peak_bytes": 4194304,
    "exit_code": 0,
    "timed_out": false
}
```

Read the snippet from a file (useful for multi-line snippets and editor workflows):

```bash
bin/altair eval --file=tools/probe.php
```

Permit writes (host-cooperative; the host must honour `ALTAIR_EVAL_ALLOW_WRITES=1`) or network egress:

```bash
bin/altair eval --writes  'container(EntityManager::class)->flush();'
bin/altair eval --network 'return file_get_contents("https://api.example.com/health");'
```

The wall-clock budget is enforced by the parent: a runaway snippet gets SIGTERM with a short grace period, then SIGKILL. Exit code is `124` (the GNU `timeout` convention) and `timed_out=true`:

```bash
bin/altair eval 'while (true) {}' --timeout-ms=300
```

```
✗ Timed out after 405ms.
  duration=405ms  memory=0 KB  exit=124
```

## Concepts

**The subprocess is the security boundary.** Every guard is enforced at the *process* level: `php -d disable_functions=...`, `php -d open_basedir=...`, `php -d memory_limit=...`, parent-managed SIGTERM/SIGKILL.

**The snippet is delivered as a separate file, not embedded into the wrapper source.** The wrapper `require`s the snippet from `<projectRoot>/.altair/eval/<uniqid>/snippet.php`, which keeps the snippet inside its own file scope, so a `})();` payload in the snippet cannot inject statements into the wrapper's file scope and therefore cannot, for example, race the wrapper's own `file_put_contents(resultFile, ...)` by `symlink()`-ing the result file path. Combined with `symlink`/`link` being in `disable_functions`, this closes the wrapper-injection class of attack the early design was vulnerable to.

**Result delivery is out-of-band.** Stdout is reserved for the snippet's own `echo` / `print` output (captured via `ob_start`) and stderr is reserved for PHP fatals / warnings. The structured payload (encoded return value, encoded exception, peak memory, duration) is written to a dedicated result file inside `<projectRoot>/.altair/eval/<uniqid>/result.json` that the parent reads after the subprocess exits. So a chatty snippet, a hard fatal, and a clean run are all distinguishable by their stream contents.

**The container helper resolves the *host's* container, not a bare framework one.** The wrapper looks for, in order: an explicit `--bootstrap=<path>` argument, the `ALTAIR_EVAL_BOOTSTRAP` env var, and finally the `config/container.php` skeleton convention (a file that returns a fully-configured `Container`). When all three miss, it falls back to a fresh `new Container()` so `container()` at least returns *something* without crashing, but the typical host gets its real bindings simply by being a real host. The bootstrap file must live inside the project root: `open_basedir` is exactly what blocks a snippet from pointing the bootstrap at `/tmp/exfil.php`.

**Each evaluation is one-shot.** No persistent container, no state carried across calls, no REPL session. If you want a REPL, use [`univeros/tinker`](./tinker.md): it is an in-process, human-driven tool that lives in the same memory as the host. Eval is the out-of-process, agent-driven complement.

**`--unsafe` is the escape hatch with an audit trail.** It lifts every ini-level guard (no `disable_functions`, no `open_basedir`, no enforced memory cap) and emits a `kind=eval` event to the mutation log so the decision is recoverable from `bin/altair events:tail`. Use it when a sandboxed run cannot answer the question (e.g. the snippet legitimately needs to spawn a subprocess); never use it by default.

## CLI surface

| Flag | Default | Effect |
|---|---|---|
| `<snippet>` (positional) | (none) | PHP code to execute. Omit and use `--file=…` instead. |
| `--file=<path>` | (none) | Read the snippet from disk. |
| `--timeout-ms=<n>` | 5000 | Wall-clock budget; clamped to `[100, 60000]`. |
| `--memory-mb=<n>` | 128 | `memory_limit`; clamped to `[16, 512]`. |
| `--writes` | off | Set `ALTAIR_EVAL_ALLOW_WRITES=1` (host-cooperative). |
| `--network` | off | Permit outbound HTTP/sockets; otherwise the network function calls are in `disable_functions` and `allow_url_fopen=0`. |
| `--unsafe` | off | **DANGEROUS**: lift every ini guard; emit an audit event. |
| `--bootstrap=<path>` | (none) | Override container bootstrap (must be inside the project root). |
| `--format=human\|json` | `human` | Output format. |

Exit code: `0` on a clean run, `1` on snippet exception / timeout / non-zero subprocess exit, `2` on usage error.

## MCP tool

[`univeros/mcp`](./mcp.md) exposes one tool:

| Tool | Input | Returns |
|---|---|---|
| `framework__eval` | `{ snippet: string (required), timeout_ms?: int, allow_writes?: bool, allow_network?: bool }` | The full `EvalResult` JSON (`ok`, `result`, `stdout`, `stderr`, `exception`, `duration_ms`, `memory_peak_bytes`, `exit_code`, `timed_out`). |

`--unsafe` is deliberately **not** exposed to MCP: lifting every ini-level guard is a CLI-only, human-or-explicitly-audited action.

## The return-value shape

`Encoder\ValueEncoder` produces a small, bounded payload:

```php
['type' => 'null',   'value' => null]
['type' => 'bool',   'value' => true]
['type' => 'int',    'value' => 42]
['type' => 'float',  'value' => 3.14]                       // 'NaN' / 'Infinity' / '-Infinity' as strings
['type' => 'string', 'value' => '...']                       // truncated past 10000 chars (length + truncated:true added)
['type' => 'array',  'is_list' => true, 'count' => 3, 'value' => [/* recursive */]]
['type' => 'object', 'class' => 'App\\…', 'id' => 7, 'properties' => [/* recursive */]]   // __debugInfo() preferred
['type' => 'iterable', 'class' => 'Generator', 'preview' => [/* first 50 items */], 'exhausted' => false]
['type' => 'reference', 'class' => 'App\\…', 'id' => 7]      // emitted when an object cycle is detected
```

Recursion stops at three levels of nesting; iterables yield at most fifty items into `preview` (and report `exhausted` so an infinite generator never overruns); strings beyond ten thousand characters are tail-truncated. So a pathological return value (a deep graph, an infinite generator, a megabyte of binary) still produces a small, agent-readable payload.

Exceptions encode as `{class, message, file, line, code, stack_trace: [...], previous: [...]}` with the trace capped at 30 frames and the `previous` chain at 10 wraps.

## Usage

### Programmatically

The `Evaluator` is the top-level orchestrator:

```php
use Altair\Eval\Evaluator;
use Altair\Eval\EvalRequest;

$result = (new Evaluator())->evaluate(new EvalRequest(
    snippet:      'return container(App\User\UserRepository::class)->count();',
    projectRoot:  getcwd(),
    timeoutMs:    5000,
    memoryLimitMb: 128,
    allowWrites:  false,
    allowNetwork: false,
    bootstrap:    null,           // null → config/container.php convention
));

$result->ok();                    // true if clean run + exit 0 + no timeout
$result->result;                  // ['type' => 'int', 'value' => 7] | null on exception
$result->exception;               // encoded exception | null
$result->stdout;                  // snippet's echo/print
$result->stderr;                  // PHP fatals / warnings
$result->durationMs;
$result->memoryPeakBytes;
$result->timedOut;                // true ⇒ exitCode 124, parent-killed
```

### Writing a host bootstrap

The skeleton-generated `config/container.php` already does this: it builds a `Container`, applies the host's Configuration chain, and `return`s the container. No special "eval bootstrap" file is needed; eval reuses the same convention.

```php
// config/container.php
<?php
declare(strict_types=1);

use Altair\Configuration\Contracts\ConfigurationInterface;
use Altair\Container\Container;

$container = new Container();

/** @var list<ConfigurationInterface> $configurations */
$configurations = require __DIR__ . '/configurations.php';
foreach ($configurations as $configuration) {
    $configuration->apply($container);
}

return $container;
```

A snippet running through eval can then `container(App\Foo::class)` and get the real, fully-wired service.

## Configuration

The `eval` CLI command builds a default `Evaluator` when none is bound, so no Container wiring is required to use it. `EvalConfiguration` is for hosts that want a specific PHP binary (e.g. an environment where `php` does not resolve to the CLI binary):

```php
use Altair\Eval\Configuration\EvalConfiguration;

(new EvalConfiguration(
    phpBinary: '/usr/local/bin/php8.3',
))->apply($container);
```

## Testing

The published tests under `tests/Eval/` are real subprocess tests; they prove the sandbox actually sandboxes, not just that the flags are present:

- [tests/Eval/Encoder/ValueEncoderTest.php](https://github.com/univeros/framework/blob/master/tests/Eval/Encoder/ValueEncoderTest.php): golden tests for every encoded shape, including object cycles, infinite generators, and depth-cap truncation.
- [tests/Eval/Encoder/ExceptionEncoderTest.php](https://github.com/univeros/framework/blob/master/tests/Eval/Encoder/ExceptionEncoderTest.php): frame rendering and chain-walking.
- [tests/Eval/Runner/SecurityProfileTest.php](https://github.com/univeros/framework/blob/master/tests/Eval/Runner/SecurityProfileTest.php): the `php -d` flag matrix for default / `--network` / `--unsafe`.
- [tests/Eval/Runner/WrapperBuilderTest.php](https://github.com/univeros/framework/blob/master/tests/Eval/Runner/WrapperBuilderTest.php): generated source contains the snippet verbatim and passes `php -l`.
- [tests/Eval/EvaluatorTest.php](https://github.com/univeros/framework/blob/master/tests/Eval/EvaluatorTest.php): full subprocess integration. Asserts that `disable_functions` blocks `exec`, that `open_basedir` blocks writes outside the project root, that a runaway loop is killed at the wall-clock deadline, and that `container()` resolves bindings from an explicit bootstrap file.
- [tests/Eval/Cli/EvalCommandTest.php](https://github.com/univeros/framework/blob/master/tests/Eval/Cli/EvalCommandTest.php): every command path including `--unsafe` event recording and `--file=…`.

When you add a guard or a security policy, mirror this pattern: write a snippet that *would* break it, run it through the real `Evaluator`, and assert the guard held.

## Related packages

- [`univeros/tinker`](./tinker.md): the in-process REPL. Use Tinker when you want a *session* against the live container; use Eval when you want a *one-shot*, structured, audit-loggable check.
- [`univeros/events`](./events.md): the append-only mutation log. `--unsafe` records a `kind=eval` event there so a "we let it write" decision is recoverable.
- [`univeros/mcp`](./mcp.md): exposes `framework__eval` (without `--unsafe`) so shell-less agents share the primitive.
- [`univeros/container`](./container.md): what `container()` resolves; the same Container the rest of the framework uses.

## Limitations

- **`eval()` the language construct cannot be disabled via `disable_functions`.** It is not a function. The subprocess sandbox (`open_basedir`, `memory_limit`, the wall-clock kill) still bounds the damage, but a snippet that calls `eval('…')` can run further PHP that the same sandbox covers.
- **Network blocking is best-effort.** `disable_functions` blocks `curl_exec` / `fsockopen` / `stream_socket_client`, and `allow_url_fopen=0` blocks the http:// stream wrappers. A determined snippet that opens raw sockets via an unblocked path is out of scope for an ini-level sandbox; kernel-level firewalling is the host's job.
- **`--writes` is host-cooperative.** PHP has no generic "this PDO connection is read-only" toggle from outside the host's wiring. The flag sets `ALTAIR_EVAL_ALLOW_WRITES=0|1` for the host's persistence Configuration to honour. A host that ignores the env var will run writes regardless.
- **The bootstrap file must live inside the project root:** that is the `open_basedir` confinement, working as intended. Point `--bootstrap` at a file under the root, or rely on the `config/container.php` convention.
- **One-shot only.** No persistent state, no REPL semantics, no shared scope across calls. If you want a session, use [`univeros/tinker`](./tinker.md).
- **`--unsafe` is unsafe.** It lifts every ini-level guard. The audit event in `.altair/events.jsonl` is the safety net, not a substitute. Treat it as a deliberate, time-bounded action.
- **The snippet inherits the parent's environment variables.** That is by design (a snippet using `container(EntityManager::class)` needs `DB_*` to be set), but it also means a snippet has read access to every secret the parent process can see (`DB_PASSWORD`, `APP_KEY`, etc.) via `getenv()`. The function-level network block makes exfiltration hard but not impossible; treat secrets in the parent process environment as exposed to the snippet's author.
- **Exception payloads leak internal file paths.** `ExceptionEncoder` includes `getFile()` / `getLine()` and the stack trace, including absolute paths into framework and vendor source. Useful for debugging; intentional path disclosure for an agent context.
- **`--file` is not confined.** The CLI's `--file=<path>` reads any path readable by the operator (no project-root check). This is a CLI surface (the operator is trusted) and is *not* exposed via the MCP tool; the MCP tool only accepts an inline `snippet`.
