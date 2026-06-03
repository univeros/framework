# Logging

> PSR-3 application logging backed by **Monolog**, wired from `LOG_*` environment variables. JSON-lines to stderr by default.

**Composer:** `univeros/logging`
**Namespace:** `Altair\Logging`

## Introduction

The framework types against `Psr\Log\LoggerInterface` in several packages — the `ExceptionHandlerMiddleware` (Http), the event `Recorder` (Events), the command-bus logger (Courier), the worker and middleware (Messaging), and the cache pool (Cache). Until you bind a logger, all of those resolve to a `NullLogger`: the framework asks for a logger and the answer is silence. A server-side 500 is logged into the void.

This package fills that gap the same way Persistence wraps Cycle and Messaging wraps Symfony Messenger: it **wraps a battle-tested library behind a contract** rather than re-implementing it. The contract here is the industry-standard PSR-3 `LoggerInterface`, and the implementation is [Monolog](https://github.com/Seldaek/monolog) — the same logger Laravel and Symfony ship. Hand-rolling a logger would mean re-deriving handlers, formatters, level handling, and processors that Monolog has had production-hardened for over a decade.

What the package adds on top of Monolog is a one-call DI wiring driven by environment variables, with an **agent-first default**: newline-delimited JSON to `stderr`. That keeps application logs machine-parseable and consistent with the rest of the framework's structured output, while a `line` format stays available for human-friendly local development.

This is the general-purpose PSR-3 sink — distinct from the framework's other structured-telemetry stories, which it does not replace:

- **Events** (`.altair/events.jsonl`) — *what mutated* (agent memory).
- **Observability** — OpenTelemetry-format traces + metrics (*how requests flow*).
- **Profiling** — CPU sampling (*where time goes*).
- **Logging** (this package) — arbitrary application log lines and third-party library logs (*the catch-all PSR-3 stream*).

## Installation

Standalone:

```bash
composer require univeros/logging
```

This pulls in `monolog/monolog`. If you are installing the full framework, `composer require univeros/framework` already includes this package.

## Configuration

Add `LoggingConfiguration` to your configuration chain — early, so packages that register a `NullLogger` fallback (e.g. Messaging) see a real logger already bound:

```php
// config/configurations.php
return [
    new Altair\Logging\Configuration\LoggingConfiguration(),
    // ... other configurations
];
```

It binds `Psr\Log\LoggerInterface` (and the concrete `Monolog\Logger`) as a shared service. Any framework package or application service that typehints `LoggerInterface` now receives a configured logger via the container.

### Environment variables

| Variable      | Default          | Purpose                                                        |
| ------------- | ---------------- | -------------------------------------------------------------- |
| `LOG_CHANNEL` | `app`            | Monolog channel name.                                          |
| `LOG_LEVEL`   | `debug`          | Minimum PSR-3 level recorded (`debug` … `emergency`).          |
| `LOG_PATH`    | `php://stderr`   | Stream or file path the handler writes to (12-factor: stderr). |
| `LOG_FORMAT`  | `json`           | `json` for newline-delimited JSON, or `line` for human output. |

An unrecognised `LOG_FORMAT` resolves to `json`; an unrecognised `LOG_LEVEL` resolves to `debug`.

### JSON output

With the default `LOG_FORMAT=json`, each record is a single JSON object per line:

```json
{"message":"order failed","context":{"order_id":42},"level":400,"level_name":"ERROR","channel":"app","datetime":"2026-06-03T14:44:53.205769+00:00","extra":{}}
```

### Human-readable output

`LOG_FORMAT=line` produces Monolog's line format, handy for `tail -f` during local development:

```
[2026-06-03T14:44:53.206751+00:00] app.WARNING: order is slow
```

## Overriding the logger

`LoggingConfiguration` binds `LoggerInterface` unconditionally — opting into it means you want this logger. To use a different PSR-3 logger (a custom Monolog setup with rotation/syslog handlers, a host framework's logger, or a test double), bind your own `LoggerInterface` in a configuration that runs **after** `LoggingConfiguration`, or simply omit `LoggingConfiguration` from the chain and bind your own.

## Relationship to error handling

When a `LoggerInterface` is bound, the Http `ExceptionHandlerMiddleware` logs every 5xx with the request method, path, and exception — so an unhandled server error produces both a structured log line here and (when Events is enabled) an `http_error` event in `.altair/events.jsonl`.
