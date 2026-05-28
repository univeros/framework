# Mcp

> A first-party Model Context Protocol server that exposes the framework as agent-callable tools, so any MCP client can build, inspect, test, and ship an Altair API through tool calls — without reading a line of source.

**Composer:** `univeros/mcp`
**Namespace:** `Altair\Mcp`

## Introduction

The rest of the framework makes your project agent-*readable* — typed code, deterministic manifests, JSON-emitting CLI commands. This package makes it agent-*drivable*. The [Model Context Protocol](https://modelcontextprotocol.io/) is the open standard MCP clients (Claude Desktop, Cursor, Zed, …) speak to local tooling: a server advertises named, typed "tools"; the client discovers them; the agent calls them as first-class actions in its conversation. You point your MCP client at `bin/altair mcp:serve` once, and from then on "add a `POST /users` endpoint that creates a user" becomes a sequence of `framework__write_spec` + `framework__scaffold` + `framework__run_tests` calls — no file reading, no shelling out.

You'll reach for this package when you want an agent to operate the project the way a developer would: list the endpoints, read a spec, scaffold a new one, run the tests, inspect a container binding, run a read-only query against the dev database. It ships **29 built-in tools** out of the box and lets you register your own with a single attribute.

The server is implemented directly on JSON-RPC 2.0 — there is no third-party MCP SDK in the dependency tree. The protocol is small (a handshake plus a few message types), and owning it keeps the wire format under your control. The protocol "brain" is decoupled from the bytes on the wire, so the same server runs over stdio (what desktop clients expect), over HTTP (for out-of-process agents), or over an in-memory transport (for tests).

## Installation

The package is bundled in the meta-package, so `composer require univeros/framework` already includes it. Standalone:

```bash
composer require univeros/mcp
```

It depends on `univeros/cli` (the `mcp:serve` / `mcp:tools` commands), `univeros/container` (tools autowire their dependencies), and the packages whose capabilities the built-in tools wrap (`univeros/scaffold`, `univeros/introspection`, `univeros/doctor`, `univeros/events`, `univeros/agent-spec`). It also pulls in `opis/json-schema` — a pure-PHP JSON Schema validator used to validate every tool call. The database tools additionally use `univeros/persistence` when it is wired; without it they report that no database is configured rather than failing.

## Quick start

Add the server to your MCP client's config once. For Claude Desktop, that's `claude_desktop_config.json`:

```json
{
  "mcpServers": {
    "altair": {
      "command": "php",
      "args": ["/path/to/project/bin/altair", "mcp", "serve"],
      "env": { "APP_ENV": "dev" }
    }
  }
}
```

From that point on the agent has the full tool palette. To see what it can call without wiring up a client, list the tools yourself:

```bash
bin/altair mcp:tools
```

That prints all 29 tools and their descriptions. For the machine-readable form (name + input/output JSON schema per tool, exactly what `tools/list` returns over the wire):

```bash
bin/altair mcp:tools --format=json
```

Start the server manually over stdio — one newline-delimited JSON-RPC message per line — to smoke-test a session by hand:

```bash
printf '%s\n' \
  '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-06-18"}}' \
  '{"jsonrpc":"2.0","id":2,"method":"tools/call","params":{"name":"framework__list_packages","arguments":{}}}' \
  | bin/altair mcp:serve
```

You'll get an `initialize` result (negotiated protocol version + server info) and a `tools/call` result whose `structuredContent` lists every installed package.

## Concepts

The server has a clean split between protocol, transport, and tools.

- **Protocol** — `Server\Server` is the pure message brain: it turns one inbound JSON-RPC message into one outbound message (or null for a notification), with no knowledge of the transport. It handles `initialize` (with protocol-version negotiation in `Server\ServerInfo`), `ping`, `tools/list`, `tools/call`, and the `notifications/*` messages. Protocol-level problems (malformed JSON, unknown method, bad params, batch requests) come back as JSON-RPC errors; a tool that throws comes back as a *successful* `tools/call` result with `isError: true` — the MCP convention that a tool failure is data the model reacts to, not a transport error.
- **Transport** — implementations of `Contracts\TransportInterface` carry the framed messages. `Transport\StdioTransport` is newline-delimited JSON over stdin/stdout (desktop clients); `Transport\HttpTransport` answers one JSON-RPC message per POST (out-of-process agents); `Transport\InMemoryTransport` queues messages for tests. `Server\ServerRunner` pumps a streaming transport: read a message, dispatch it, write the reply, repeat.
- **Tools** — every tool is a class implementing `Contracts\McpToolInterface` (`call(array $input): array`) and carrying the `Attribute\McpTool` attribute for its name, description, and input/output JSON schema paths. `Tool\AttributeToolDiscoverer` reads the attribute into a `Tool\ToolDescriptor`; `Tool\ToolRegistry` holds the set (name-sorted for stable `tools/list` output); `Tool\ContainerToolResolver` instantiates a tool through the Container at call time, so a tool's constructor dependencies are autowired exactly like a CLI command's.
- **Schemas** — `Schema\SchemaValidator` validates a tool's `arguments` against its input schema (via opis/json-schema) before the tool runs. Built-in schemas live in `src/Altair/Mcp/Schema/*.json` and are published verbatim through `tools/list`.

The guardrails are what make it safe to point an agent at your filesystem:

- `Guard\PathGuard` blocks writes to `vendor/`, `.git/`, `composer.json`, `composer.lock`, and any `.env*` file, and confines reads/spec-loads to the project root (lexical, traversal-safe).
- `Guard\ServerMode` is the mutation policy set at startup: `--readonly` makes the whole server inspect-only; database writes additionally require `--allow-writes`.
- `Database\SqlReadGuard` restricts `framework__db_query` to a single read-only `SELECT`/`WITH` and rejects writes, DDL, statement chaining, and `INTO OUTFILE`/`DUMPFILE`.

## Usage

### The built-in tools

All 29 tools use the `framework__` prefix, take JSON arguments, and return JSON. They fall into five groups:

**Discovery / inspection** — `list_packages`, `describe_package`, `list_specs`, `read_spec`, `list_endpoints`, `describe_endpoint`, `container_resolve`, `list_commands`.

**Generation / mutation** — `write_spec` (validates before writing), `scaffold`, `rewind_spec`, `emit_openapi`, `emit_sdk`.

**Verification** — `doctor`, `run_tests`, `check_drift`, `phpstan`.

**Database** (read-only by default) — `db_query`, `db_schema`, `db_migrate`, `plan_migration`.

**Introspection** — `container_inspect`, `config_dump` (secrets always masked), `routes_list`, `route_show`, `listeners_list`, `listener_show`, `middleware_list`, `manifest_diff`.

Each one wraps the real framework API — `framework__scaffold` drives `univeros/scaffold`, `framework__doctor` drives `univeros/doctor`, the `framework__*_inspect`/`*_list` tools drive `univeros/introspection` — so the tool surface and the CLI surface stay in lock-step.

### Running the server

The stdio transport is the common case and the default:

```bash
bin/altair mcp:serve
```

For an out-of-process agent, switch to HTTP (one JSON-RPC message per POST):

```bash
bin/altair mcp:serve --transport=http --host=127.0.0.1 --port=3737
```

Lock the server down when you only want inspection, or opt into gated database writes:

```bash
bin/altair mcp:serve --readonly          # no writes at all
bin/altair mcp:serve --allow-writes      # permit db:migrate via framework__db_migrate
```

### Writing a custom tool

A tool is a plain service: implement `McpToolInterface` and decorate it with `#[McpTool]`. The input has already been validated against your schema before `call()` runs, and the Container autowires your constructor — so you can depend on any bound service.

```php
use Altair\Mcp\Attribute\McpTool;
use Altair\Mcp\Contracts\McpToolInterface;
use Override;

#[McpTool(
    name: 'app__greet',
    description: 'Return a greeting for the given name.',
    inputSchema: __DIR__ . '/Schema/greet-input.json',
)]
final readonly class GreetTool implements McpToolInterface
{
    #[Override]
    public function call(array $input): array
    {
        return ['greeting' => 'Hello, ' . ($input['name'] ?? 'world')];
    }
}
```

`inputSchema` is an absolute path to a JSON Schema file (the attribute is evaluated at compile time, so use `__DIR__ . '/…'` rather than a function call). Point the server at the directory holding your tools with the `MCP_TOOL_PATHS` environment variable (`PATH_SEPARATOR`-delimited) and `AttributeToolDiscoverer` finds them at startup. Throw `Altair\Mcp\Exception\GuardrailException` from a tool when an operation should be refused — the server surfaces its message to the agent as a tool error rather than a crash.

## Configuration

`Configuration\McpConfiguration` wires everything into the Container: the tool registry (built-in tools from `Tool\BuiltinTools` plus any discovered user tools), the protocol services, the guardrails, and the read-only database gateway (`Database\CycleDatabaseGateway` when `univeros/persistence` is bound, otherwise `Database\NullDatabaseGateway`). It applies the prerequisite Configurations (events, scaffold journal, doctor) when they are absent, so tool dependencies resolve, and binds the Container to itself so tools resolve through the real instance.

You rarely construct it by hand — `mcp:serve` and `mcp:tools` apply it for you, passing the `ServerMode` derived from the command flags. Construct it directly only when embedding the server in another process:

```php
use Altair\Container\Container;
use Altair\Mcp\Configuration\McpConfiguration;
use Altair\Mcp\Guard\ServerMode;
use Altair\Mcp\Server\Server;

$container = new Container();
(new McpConfiguration(mode: new ServerMode(readonly: true)))->apply($container);

/** @var Server $server */
$server = $container->make(Server::class);
$reply = $server->handle('{"jsonrpc":"2.0","id":1,"method":"tools/list"}');
```

See [container.md](./container.md) for the binding API.

## Testing

The tests under `tests/Mcp/` are the clearest description of the server's behaviour:

- `tests/Mcp/Server/ServerTest.php` — the protocol surface: handshake, ping, tool listing, tool invocation, and every error path (parse error, unknown method, bad params, guardrail-as-tool-error).
- `tests/Mcp/Tool/*ToolsTest.php` — each tool group, with golden input/output cases and the graceful-degradation paths (e.g. an introspection tool returning `{available: false}` when its host collaborator isn't bound).
- `tests/Mcp/McpServerIntegrationTest.php` — a full agent-style session driven through `InMemoryTransport` against a Container-built `Server`: `initialize`, `tools/list`, then real `tools/call`s.

When you add a tool, mirror that last pattern: build the registry, drive a session over the in-memory transport, and assert the structured result — it exercises validation, resolution, and the protocol envelope together.

## Related packages

- [cli.md](./cli.md) — `mcp:serve` and `mcp:tools` are attribute-driven commands discovered by the framework CLI.
- [scaffold.md](./scaffold.md) — the `framework__write_spec` / `scaffold` / `rewind_spec` / `emit_openapi` / `emit_sdk` tools wrap it.
- [introspection.md](./introspection.md) — the `framework__container_inspect` / `routes_*` / `listeners_*` / `middleware_list` / `config_dump` / `manifest_diff` tools wrap its inspectors.
- [doctor.md](./doctor.md) — `framework__doctor` wraps the health-check runner.
- [events.md](./events.md) — mutating tools record what they changed to the `.altair/events.jsonl` log.
- [agent-spec.md](./agent-spec.md) — `framework__describe_package` surfaces package shape; AgentSpec is the agent-*readable* counterpart to this agent-*drivable* server.
- [persistence.md](./persistence.md) — backs the `framework__db_*` tools when wired.

## Limitations

- This is a **local developer tool** that runs as your user with your filesystem permissions — that's the accepted trust model. The guardrails defend against an agent doing more than intended; they are not a sandbox against a hostile operator.
- One instance per project. Hosting, multi-tenancy, and authentication on the HTTP transport are out of scope for v1 (the HTTP transport binds to `127.0.0.1` and assumes a same-trust-boundary caller).
- The database tools require `univeros/persistence` to be wired; without it `db_query` / `db_schema` report that no database is configured, and `db_migrate` is gated behind `--allow-writes`.
- `config_dump` masks secrets by **key name** (e.g. anything containing `PASSWORD`, `TOKEN`, `DSN`, …). A secret stored under a non-matching key — or inline in a URL value — can still surface; treat the output as sensitive.
- The server implements MCP **tools** only; the protocol's "resources" and "prompts" primitives are not exposed in v1.
