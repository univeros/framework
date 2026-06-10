# Observatory

> A dev-only web monitoring panel for Altair apps (health, activity, queues, routes, config and more), in the spirit of Laravel Telescope/Horizon/Pulse, but as a thin, gated layer over data the framework already produces.

**Composer:** `univeros/observatory`
**Namespace:** `Altair\Observatory`

## Introduction

Every framework eventually needs a window into the running app: is it healthy, what just happened, what's stuck in the queue, which routes exist. Observatory is that window. The key design choice is that it owns **no data of its own**: `doctor` (health), `events` (the append-only activity/error log), `messaging` (queues and failed jobs), `introspection` (routes, container, config, listeners, middleware) and `persistence` (migrations) already expose everything, and `univeros/mcp` already wraps those same sources for agents. Observatory is simply the *human* consumer of that data: an agent reads via MCP, a developer reads via the panel.

That makes the package a presentation layer with near-zero coupling to the core. A **panel** is a pure data provider: it reads one source and projects a render-agnostic `PanelSnapshot` (a status, a headline, a flat metrics map, detail rows). The same snapshot backs the web UI and any JSON endpoint, so panels stay unit-testable and never bind to HTML.

Because the panel surfaces configuration, queues and database state, **access is fail-closed**: it is denied unless explicitly enabled *and* running in a non-production environment, so a misconfigured production deploy never exposes it.

> **Status:** complete. Panels: `runtime`, `health` (doctor), `events`/activity, `queues` (messaging), `routes`/`container`/`config` (introspection), `migrations` (persistence). `DashboardHandler` serves the gated, dark-first card overview *and* per-panel detail views (`?panel=<id>`); `ActivityStreamHandler` streams the live activity tail over SSE.

## Installation

Standalone:

```bash
composer require --dev univeros/observatory
```

In the monorepo it ships with the framework. Register the Configuration and gate it via env:

```dotenv
OBSERVATORY_ENABLED=true   # default: false (off)
APP_ENV=local              # panel served only in local/development/dev/testing
```

## Quick start

Resolve the facade from the container and project the dashboard as plain data:

```php
use Altair\Observatory\Observatory;

$observatory = $container->get(Observatory::class);

if ($observatory->isAccessible()) {
    foreach ($observatory->dashboard() as $id => $card) {
        // $card = ['label' => ..., 'icon' => ..., 'snapshot' => ['status' => 'ok', ...]]
    }
}
```

## Concepts

**A panel is a data provider, not a view.** `PanelInterface` is `id()`, `label()`, `icon()` and `snapshot(): PanelSnapshot`. Rendering is the UI layer's job, so the same panel serves HTML and JSON.

**Snapshots are render-agnostic read models.** `PanelSnapshot` carries a `PanelStatus` (`ok`/`warning`/`critical`/`unknown`), a one-line `headline`, a flat `metrics` map for the card header, and an ordered list of `items` (detail rows). `toArray()` is the wire/JSON shape.

**The registry is id-keyed and overridable.** `PanelRegistry` keys panels by `id()`; registering an existing id replaces it, so a host can override a built-in panel by registering its own after configuration (`prepare()`-ing the shared `PanelRegistry`).

**Access is a swappable contract.** `AccessGuardInterface::allows()` decides whether the panel may be served. The default `EnvironmentAccessGuard` is fail-closed (enabled flag AND allow-listed environment). Hosts rebind it for real auth (IP allow-list, signed cookie, RBAC) without touching panels.

## Configuration

`ObservatoryConfiguration` wires the access guard, the panel registry (with the built-in `runtime` panel) and the `Observatory` facade into the container. It reads:

| Env | Default | Effect |
|---|---|---|
| `OBSERVATORY_ENABLED` | `false` | Master on/off switch. |
| `APP_ENV` | `production` | Panel served only when in `local`/`development`/`dev`/`testing`. |

## Security

Observatory exposes sensitive surfaces (config, queues, database). The guard is **fail-closed**: an unset flag or an unrecognised/production environment denies access. When the UI layer lands it will additionally reuse the introspection `config_dump` secret masking, and hosts are expected to put a real auth guard in front in any shared environment.

## Testing

The data layer is plain PHP with no I/O, so panels and the facade test directly:

```php
$observatory = new Observatory(
    new PanelRegistry([new RuntimePanel()]),
    new EnvironmentAccessGuard(enabled: true, environment: 'local'),
);

self::assertTrue($observatory->isAccessible());
self::assertSame('ok', $observatory->dashboard()['runtime']['snapshot']['status']);
```

## Extending

Implement `PanelInterface`, returning a `PanelSnapshot` from `snapshot()`, and register it on the shared `PanelRegistry`:

```php
final class QueuesPanel implements PanelInterface
{
    public function __construct(private readonly MessageBusInterface $bus) {}
    public function id(): string { return 'queues'; }
    public function label(): string { return 'Queues'; }
    public function icon(): string { return 'queue-list'; }
    public function snapshot(): PanelSnapshot { /* read failed/transports, map to a snapshot */ }
}
```

`RuntimePanel` is the worked reference implementation (it depends on nothing outside PHP).

## Serving the dashboard

`DashboardHandler` is a PSR-15 request handler. Route a path to it (it autowires
`Observatory`, the renderer, and your PSR-17 response/stream factories):

```php
$response = $container->get(DashboardHandler::class)->handle($request);
// 200 + HTML when accessible; 403 + "disabled" page otherwise.
```

`?panel=<id>` renders that panel's detail view (a filterable table of its rows,
404 on an unknown id) while the bare path renders the card overview.

The `queues` and `migrations` panels read through framework-owned seams:
`FailedQueueReaderInterface` and `MigrationStatusReaderInterface`. Bind a host
adapter (Messenger failure transport / Cycle migrator) for live data; absent a
binding the panel simply isn't registered (the dashboard shows fewer cards).

### Live activity tail (SSE)

`ActivityStreamHandler` streams the `.altair/events.jsonl` log to the activity
panel over Server-Sent Events. Rather than holding a long-lived connection
(which would pin a PHP worker), it emits the events newer than the client's
`Last-Event-ID` and closes; the browser's `EventSource` reconnects with the last
id it saw, so the tail stays near-real-time with no extra infrastructure. Route
a second path to it and pass that URL to the dashboard handler's `$streamUrl` so
the activity detail view can subscribe:

```php
// GET /_observatory/stream → ActivityStreamHandler (gated by the same guard)
$response = $container->get(ActivityStreamHandler::class)->handle($request);
```

## Related packages

- [doctor.md](./doctor.md): health checks (the `health` panel's source).
- [events.md](./events.md): the append-only activity/error log (the `events` panel + the SSE tail).
- [messaging.md](./messaging.md): queues and failed jobs (the `queues` panel's source, via the reader seam).
- [persistence.md](./persistence.md): migration status (the `migrations` panel's source, via the reader seam).
- [introspection.md](./introspection.md): routes, container, config, listeners, middleware.
- [mcp.md](./mcp.md): the agent-facing consumer of the same data sources (Observatory is the human one).

## Limitations

- **Dev-only by design.** The default guard denies in production and whenever `OBSERVATORY_ENABLED` is unset: it is environment-based, not auth-based, so put real authentication in front before exposing it anywhere shared. Point production observability (metrics/tracing) at your APM instead.
- The SSE activity tail **emits-and-closes** (the client reconnects); it is near-real-time, not a persistent push channel, on purpose, so it never pins a worker.
- The `queues` and `migrations` panels need a host-bound reader adapter to show live data; without one they are simply absent from the dashboard.
- Panels describe **state** ("right now"), not history or trends; use [events.md](./events.md) for the chronological record.
- The `resources/views/*` templates are presentation-only (HTML in PHP) and are excluded from the framework's static analysis; treat them as the view layer, not application logic.
