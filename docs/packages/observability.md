# Observability

> Framework-native runtime observability. OpenTelemetry-format spans + metrics emitted *natively* (no OTel SDK dependency): a PSR-15 middleware for per-request tracing, a JSONL log under `.altair/observability/` for local inspection, and an `OtlpJsonExporter` that POSTs to any OTel Collector. Tail and stats in the CLI; tail and stats in the MCP.

**Composer:** `univeros/observability`
**Namespace:** `Altair\Observability`

## Introduction

#71 (introspection) answers *"what is wired?"*: a read-once, no-overhead surface. Observability is its runtime counterpart: *"what is happening **now**?"* Continuous emission, real overhead, separate UX. So it lives in its own package, off by default, with the host opting in by applying `ObservabilityConfiguration`.

The design choice that keeps it lean: the v1 package speaks the OTel **wire format** (OTLP-JSON over HTTP) natively, but does not pull in the full OpenTelemetry SDK. The OTLP shape is well-documented and stable; a thin native exporter that POSTs the right JSON to `/v1/traces` and `/v1/metrics` gets you OTel Collector compatibility without dragging in gRPC, protobuf, and a transitive dependency tree. A full-SDK adapter is a documented follow-up for hosts that want it.

Observability owns the *data*. The naturally-paired UI is the existing [`univeros/observatory`](./observatory.md) (a presentation layer that owns no data of its own); a future observatory panel can read `.altair/observability/<date>.jsonl` exactly the way its existing panels read events, queues, and routes.

## Installation

```bash
composer require --dev univeros/observability
```

Then apply the Configuration in your host bootstrap and wrap the middleware around your HTTP pipeline:

```php
use Altair\Observability\Configuration\ObservabilityConfiguration;
use Altair\Observability\Middleware\ObservabilityMiddleware;

(new ObservabilityConfiguration())->apply($container);

// Add to the top of your PSR-15 middleware pipeline:
$pipeline->add($container->make(ObservabilityMiddleware::class));
```

That's the only host wiring required. Spans and metrics now land in `.altair/observability/<date>.jsonl`; `bin/altair observability:tail` and `:stats` work immediately.

## Quick start

Inspect what's been captured:

```bash
bin/altair observability:tail            # newest 50 spans + metrics
bin/altair observability:tail --kind=span --limit=200
bin/altair observability:stats           # p50/p95/p99, error rate, top errors, counters
```

Forward to an OpenTelemetry Collector (one-shot):

```bash
bin/altair observability:export http://collector:4318
```

JSON output for agents / CI:

```bash
bin/altair observability:tail --format=json
bin/altair observability:stats --format=json
```

## Concepts

**Spans, metrics, exporters: the OTel data model**, expressed as small framework-native VOs (`Span`, `MetricPoint`). Hex trace ids (16 bytes) and span ids (8 bytes) so the wire format is OTel-spec exact; timestamps as absolute unix nanoseconds; attributes as scalar / scalar-list values. Every internal VO has `toArray()` for the JSONL log and a `fromArray()` so the JSONL can be re-hydrated and forwarded.

**The Tracer is a stack**, not a context propagation library. Spans you open inside a request become children of whatever is currently open in the same `Tracer`; the middleware opens the root server span at the top of the pipeline so anything else you instrument inside (a domain operation, a database call) hangs off it without explicit parent linkage.

**Exporters are pluggable**, and the v1 ships three:

- `JsonLogExporter`: appends to `.altair/observability/<date>.jsonl` (one JSON object per line, discriminated by `_kind: span|metric`). The default; perfect for `tail -f` and offline forensics.
- `OtlpJsonExporter`: POSTs OTLP-JSON to `<endpoint>/v1/traces` and `<endpoint>/v1/metrics`. Curl-based, no PSR-18 dependency; failures log to an optional file rather than throwing (losing telemetry must never break the app).
- `StdoutExporter`: one-line-per-event dev output (`[span ] ✓ HTTP GET 20.8ms trace=5584f25e`).

**The Recorder buffers and bounds**. `InMemoryRecorder` holds spans and metric points up to `maxBufferedSpans` / `maxBufferedPoints` (defaults 1k / 5k). When the cap is hit and an exporter is bound, it auto-flushes; when no exporter is bound, the oldest entries roll off. So a long-running worker without an exporter never grows its buffer beyond the cap.

**Resource attributes are static**, stamped once per `OtlpJsonExporter` instance (default `service.name=altair`). Per-span attributes are dynamic. This matches OTel's resource/scope/span split exactly; a Collector sees the same shape it would from any spec-compliant client.

## What each exporter actually produces

Given a single HTTP GET request through the middleware:

**`StdoutExporter`:** for dev tail:
```
[span ] ✓ HTTP GET  20.8ms  trace=5584f25e
[counter] http.server.requests = 1
```

**`JsonLogExporter`:** one JSON per line in `.altair/observability/<date>.jsonl`:
```json
{"_kind":"span","trace_id":"5584f25e89e4da068b9678aee117d822","span_id":"a4d4d4c6501c6d8e","parent_span_id":null,"name":"HTTP GET","kind":2,"start_unix_nano":1780088478973381120,"end_unix_nano":1780088478994185984,"duration_ms":20.8,"status":{"code":1,"message":null},"attributes":{"http.request.method":"GET","http.response.status_code":200}}
{"_kind":"metric","name":"http.server.requests","kind":"counter","value":1,"unix_nano":1780088478994667008,"attributes":{"http.method":"GET","http.status_code":200},"unit":null,"description":null}
```

**`OtlpJsonExporter`:** POSTed to `<endpoint>/v1/traces`:
```json
{
  "resourceSpans": [{
    "resource": {"attributes": {"service.name": "altair"}},
    "scopeSpans": [{
      "scope": {"name": "altair/observability", "version": "1.0"},
      "spans": [{
        "traceId": "5584f25e89e4da068b9678aee117d822",
        "spanId": "a4d4d4c6501c6d8e",
        "parentSpanId": null,
        "name": "HTTP GET",
        "kind": 2,
        "startTimeUnixNano": "1780088478973381120",
        "endTimeUnixNano": "1780088478994185984",
        "attributes": {"http.request.method": "GET", "http.response.status_code": 200},
        "status": {"code": 1, "message": null}
      }]
    }]
  }]
}
```

(Metrics mirror the same shape under `resourceMetrics → scopeMetrics → metrics`.)

## CLI surface

| Command | Effect |
|---|---|
| `observability:tail [--limit] [--kind=span\|metric] [--format]` | Newest spans + metrics from the JSONL log. |
| `observability:stats [--limit] [--format]` | Span counts, error rate, p50/p95/p99 durations, top errors, counter totals. |
| `observability:export <otlp-endpoint> [--limit] [--service-name] [--format]` | One-shot forward of recent JSONL log to an OTLP/HTTP collector, batched. |

## MCP tools

[`univeros/mcp`](./mcp.md) exposes two read-only tools; the server now serves **42 tools**:

| Tool | Wraps | Returns |
|---|---|---|
| `framework__observability_tail` | `observability:tail` | `{count, rows: [...]}` |
| `framework__observability_stats` | `observability:stats` | `{spans, errors, error_rate, duration_ms, top_errors, counters}` |

## Usage

### Programmatically

```php
use Altair\Observability\Metrics\Meter;
use Altair\Observability\Recorder\InMemoryRecorder;
use Altair\Observability\Trace\SpanKind;
use Altair\Observability\Trace\Tracer;

$recorder = $container->make(InMemoryRecorder::class);
$tracer = $container->make(Tracer::class);
$meter = $container->make(Meter::class);

$context = $tracer->start('checkout.process', SpanKind::Internal, ['order.id' => 42]);
try {
    $cart->checkout();
    $tracer->end($context);
} catch (\Throwable $e) {
    $tracer->end($context, \Altair\Observability\Trace\SpanStatus::Error, $e->getMessage());
}

$meter->counter('checkouts.total', 1.0, ['outcome' => 'ok']);
$meter->histogram('checkout.duration_ms', 123.4, unit: 'ms');

$recorder->flush();   // explicit flush; recorder also auto-flushes at buffer caps
```

Or, the convenience helper that wraps a callable:

```php
$tracer->span('cache.lookup', fn() => $cache->get('users:42'));
```

### Forwarding to a Collector

```php
use Altair\Observability\Configuration\ObservabilityConfiguration;
use Altair\Observability\Exporter\OtlpJsonExporter;

(new ObservabilityConfiguration(
    exporter: new OtlpJsonExporter(
        endpoint: 'http://collector:4318',
        resourceAttributes: ['service.name' => 'orders-api', 'deployment.env' => 'production'],
        errorLogFile: '/var/log/altair/otlp-errors.log',
    ),
))->apply($container);
```

The recorder auto-flushes to the OTLP exporter on buffer cap; you can also call `$recorder->flush()` explicitly after a long operation or on shutdown.

## Testing

- `tests/Observability/Trace/TracerTest.php`: span lifecycle, parent-child, out-of-order ends, span()-helper success + exception paths.
- `tests/Observability/Metrics/MeterTest.php`: counter / gauge / histogram emission.
- `tests/Observability/Recorder/InMemoryRecorderTest.php`: flush, auto-flush at cap, rolling drop when no exporter, no-op flush.
- `tests/Observability/Exporter/JsonLogExporterTest.php`: line-per-event shape, day-file append, empty-batch no-op.
- `tests/Observability/Middleware/ObservabilityMiddlewareTest.php`: PSR-15 happy path, 5xx as Error, thrown exception captured and re-thrown.

## Related packages

- [`univeros/observatory`](./observatory.md): the natural human consumer. A future Observatory `traces` / `metrics` panel reads from `.altair/observability/<date>.jsonl` the same way the existing panels read from events, queues, and routes.
- [`univeros/profiling`](./profiling.md): Profiling answers *"where does my code spend time?"* (sampling, call tree, hotspots, diff); Observability answers *"what is happening **now**?"* (per-request tracing + metrics, OTel-shape). Profiling is dev-loop; Observability is prod-shape.
- [`univeros/events`](./events.md): Events is the append-only mutation log (what *changed*); Observability is the runtime telemetry stream (what *ran*). Different questions, different audiences.

## Limitations

- **No full OpenTelemetry SDK integration in v1.** The wire format (OTLP-JSON over HTTP) is implemented natively; a full-SDK adapter (gRPC OTLP, SpanProcessor pipeline, propagators) is a follow-up for hosts that want the OTel SDK contract end-to-end.
- **No container / messenger / DB auto-instrumentation in v1.** The HTTP middleware is the only auto-instrumented surface; in-app spans + metrics are explicit (call `Tracer::start()`/`Meter::counter()`). A `ContainerInspector` decorator for `make()` timing and a Messenger middleware are documented follow-ups.
- **No Prometheus pull exporter in v1.** Push-only (OTLP/HTTP, JSONL). A Prometheus `/metrics` HTTP endpoint is a documented follow-up.
- **Histogram bucketing is deferred to the collector.** v1 records raw observations; the OTLP collector (or a downstream aggregator) handles bucket math.
- **No span sampling in v1.** Everything emitted is recorded. A `SamplerInterface` (alwaysOn / probabilistic / parent-based) is a follow-up for high-throughput hosts.
- **Resource attributes are static per exporter instance.** Dynamic resource detection (e.g. populate `host.name` from the OS) is a follow-up.
