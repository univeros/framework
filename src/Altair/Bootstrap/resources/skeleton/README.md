# Your new Univeros app

A runnable [Univeros](https://github.com/univeros/framework) API generated from the official starter — one working endpoint (`GET /ping`), one passing test, the spec-driven toolchain wired, and the Altair agent skill staged at `.ai/skills/altair/SKILL.md` and `.claude/skills/altair/SKILL.md` so any MCP-capable agent that opens the project is onboarded on first load.

> **Landed here from GitHub?** This repo is the starter template — create a new app from it with:
> ```bash
> composer create-project univeros/univeros myapp
> ```
> The repo is a read-only mirror of `src/Altair/Bootstrap/resources/skeleton/` in [univeros/framework](https://github.com/univeros/framework). Open issues and PRs there.

## Requirements

- PHP 8.3+
- Composer

## Getting started

```bash
composer serve            # php -S localhost:8080 -t public
curl localhost:8080/ping  # {"message":"ok","timestamp":"..."}
composer test
```

## Building endpoints

Endpoints are spec-driven — don't hand-write the boilerplate. Write a YAML spec under `api/`, then scaffold:

```bash
vendor/bin/altair spec:scaffold api/your-endpoint.yaml
# Writes: Action, Input, Responder, domain stub, PHPUnit test,
#         route entry, OpenAPI fragment. (Add a `persistence:` block
#         to also emit a Cycle entity + migration + repository.)
```

Implement the generated domain's `__invoke()` — the one piece left as a TODO — and your endpoint is live. See `api/ping.yaml` + `app/Health/Ping.php` for the reference shape.

Useful commands (all via `vendor/bin/altair`):

```bash
vendor/bin/altair spec:scaffold api/         # scaffold every spec
vendor/bin/altair spec:emit-openapi          # merge OpenAPI fragments
vendor/bin/altair spec:lint                  # spec-vs-code drift gate
vendor/bin/altair doctor                     # health checks (JSON or human)
vendor/bin/altair journal:rewind             # undo the last scaffold
vendor/bin/altair events:since-last-success  # what changed since the last OK
vendor/bin/altair index:find-usages Foo\\Bar # symbol-usage index
vendor/bin/altair mcp:serve                  # drive this project from an MCP client
```

## Layout

```
app/                    your application code (App\ namespace)
api/                    YAML endpoint specs
config/                 container, configuration chain, routes
public/                 front controller (index.php)
tests/                  PHPUnit tests
docs/openapi/           emitted OpenAPI fragments
database/migrations/    Cycle migrations (when persistence: is used)
.ai/skills/altair/      Altair skill for any MCP-capable agent
.claude/skills/altair/  Claude Code variant of the same skill
.altair/                local-only: event log, journal, symbol index (gitignored)
```

## Driving it from an AI agent

This project ships two flavours of agent affordance, depending on whether the agent has shell access:

**Shell-capable agents** (Claude Code, Cursor, Copilot Workspace) auto-load the Altair skill at `.ai/skills/altair/SKILL.md` (and `.claude/skills/altair/SKILL.md` for Claude Code specifically). The skill gives the agent project-specific operating instructions on first load — what `bin/altair` ships, when to scaffold vs. hand-edit, how the event log works.

**Shell-less / MCP-only agents** (ChatGPT desktop, Claude desktop, third-party MCP hosts) connect via the framework's MCP server. Point your MCP client at this project:

```json
{
  "mcpServers": {
    "altair": {
      "command": "php",
      "args": ["vendor/bin/altair", "mcp:serve"],
      "env": { "APP_ENV": "dev" }
    }
  }
}
```

The server exposes 42 framework tools — scaffold, doctor, journal, events, index, suggest, eval — so the agent can drive the project without a shell.

## Learn more

- **[univeros/framework](https://github.com/univeros/framework)** — the library this depends on, source for `bin/altair`, contributing guidelines
- **[univeros/docs](https://github.com/univeros/docs)** — per-package guides
- **[docs/openapi/](docs/openapi/)** — the OpenAPI fragments emitted from your specs

## License

This starter is licensed under the [MIT license](https://opensource.org/licenses/MIT). The application code you write on top of it is yours.
