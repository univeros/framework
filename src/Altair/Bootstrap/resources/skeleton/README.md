# Altair API

A runnable Altair Framework API, generated from `univeros/skeleton`. It ships
one working endpoint (`GET /ping`), one passing test, and the spec-driven
toolchain wired and ready.

## Requirements

- PHP 8.3+
- Composer

## Getting started

Install dependencies and serve:

```bash
composer install
composer serve            # php -S localhost:8080 -t public
curl localhost:8080/ping  # {"message":"ok","timestamp":"..."}
```

Run the tests:

```bash
composer test
```

## Building endpoints

Endpoints are spec-driven. Write a YAML spec under `api/`, then scaffold the
Action / Input / Domain / Responder / test / OpenAPI fragment / route entry:

```bash
vendor/bin/altair spec:scaffold api/your-endpoint.yaml
```

Implement the generated domain's `__invoke()` (the one piece left as a TODO),
and your endpoint is live. See `api/ping.yaml` + `app/Health/Ping.php` for the
shape.

Useful commands (all via `vendor/bin/altair`):

```bash
vendor/bin/altair spec:scaffold api/         # scaffold every spec
vendor/bin/altair spec:emit-openapi          # merge the OpenAPI document
vendor/bin/altair spec:lint                  # spec-vs-code drift gate
vendor/bin/altair doctor                     # project health checks
vendor/bin/altair mcp:serve                  # drive this project from an MCP client
```

## Layout

```
app/         your application code (App\ namespace)
api/         YAML endpoint specs
config/      container, configuration chain, routes
public/      front controller (index.php)
tests/       PHPUnit tests
docs/openapi/ emitted OpenAPI fragments
database/migrations/ migrations (when using an ORM)
```

## Driving it from an AI agent

Point your MCP client at this project and the framework exposes its tools
(`framework__scaffold`, `framework__run_tests`, …):

```json
{
  "mcpServers": {
    "altair": {
      "command": "php",
      "args": ["vendor/bin/altair", "mcp", "serve"],
      "env": { "APP_ENV": "dev" }
    }
  }
}
```
