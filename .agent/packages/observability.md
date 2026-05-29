# univeros/observability  ·  Altair\Observability

**Purpose:** bin/altair observability:* — framework-native runtime observability. OpenTelemetry-compatible spans + metrics emitted natively (OTLP-JSON over HTTP, no SDK dependency), a PSR-15 middleware for per-request tracing, and a JSONL log under .altair/observability/ for local inspection and after-the-fact analysis.

## Public contracts

| Interface | Method | Returns | Notes |
|---|---|---|---|
| `ExporterInterface` | `export(array, array)` | `void` |  |
| `RecorderInterface` | `flush()` | `void` |  |
|  | `recordMetric(MetricPoint)` | `void` |  |
|  | `recordSpan(Span)` | `void` |  |

## Concrete classes

- `ExportCommand` _(final)_
- `InMemoryRecorder` _(final)_ — implements `RecorderInterface`
- `Json` _(final)_
- `JsonLogExporter` _(final)_ — implements `ExporterInterface`
- `JsonLogReader` _(final)_
- `Meter` _(final)_
- `MetricKind` _(final)_ — implements `BackedEnum`, `UnitEnum`
- `MetricPoint` _(final)_
- `ObservabilityConfiguration` _(final)_ — implements `ConfigurationInterface`
- `ObservabilityMiddleware` _(final)_ — implements `MiddlewareInterface`
- `OtlpJsonExporter` _(final)_ — implements `ExporterInterface`
- `PendingSpan` _(final)_
- `Span` _(final)_
- `SpanContext` _(final)_
- `SpanKind` _(final)_ — implements `BackedEnum`, `UnitEnum`
- `SpanStatus` _(final)_ — implements `BackedEnum`, `UnitEnum`
- `StatsCommand` _(final)_
- `StdoutExporter` _(final)_ — implements `ExporterInterface`
- `TailCommand` _(final)_
- `Tracer` _(final)_

## Tests as documentation

- `tests/Observability/Exporter/JsonLogExporterTest.php`
- `tests/Observability/Metrics/MeterTest.php`
- `tests/Observability/Middleware/ObservabilityMiddlewareTest.php`
- `tests/Observability/Recorder/InMemoryRecorderTest.php`
- `tests/Observability/Trace/TracerTest.php`

## Related packages

- `psr/http-message`
- `psr/http-server-handler`
- `psr/http-server-middleware`
- `univeros/cli`
- `univeros/configuration`
- `univeros/container`
