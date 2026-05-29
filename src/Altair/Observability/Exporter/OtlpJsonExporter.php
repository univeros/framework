<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Observability\Exporter;

use Altair\Observability\Contracts\ExporterInterface;
use Altair\Observability\Metrics\MetricPoint;
use Altair\Observability\Trace\Span;
use Override;

/**
 * Posts spans + metrics to an OTLP-JSON HTTP endpoint (`/v1/traces` for
 * spans, `/v1/metrics` for metrics) — the on-wire format an OpenTelemetry
 * Collector accepts via its OTLP/HTTP receiver.
 *
 * Resource attributes (e.g. `service.name`) are set once via the constructor
 * and stamped onto every exported batch. The exporter is intentionally
 * curl-based with no PSR-18 dependency so it stays light; failures are
 * captured into the optional error log rather than thrown — losing telemetry
 * must never break the application.
 *
 * v1 ships an OTLP-shaped payload (resourceSpans → scopeSpans → spans) with a
 * pragmatic flatter attribute encoding; a strictly-typed-attribute encoder
 * (OTLP `AnyValue` variants) is a documented follow-up.
 */
final readonly class OtlpJsonExporter implements ExporterInterface
{
    /**
     * @param array<string, scalar> $resourceAttributes
     */
    public function __construct(
        private string $endpoint,
        private array $resourceAttributes = ['service.name' => 'altair'],
        private int $timeoutSeconds = 5,
        private ?string $errorLogFile = null,
    ) {}

    #[Override]
    public function export(array $spans, array $metrics): void
    {
        if ($spans !== []) {
            $this->post(rtrim($this->endpoint, '/') . '/v1/traces', $this->encodeTraces($spans));
        }

        if ($metrics !== []) {
            $this->post(rtrim($this->endpoint, '/') . '/v1/metrics', $this->encodeMetrics($metrics));
        }
    }

    /**
     * @param list<Span> $spans
     *
     * @return array<string, mixed>
     */
    private function encodeTraces(array $spans): array
    {
        return [
            'resourceSpans' => [[
                'resource' => ['attributes' => $this->resourceAttributes],
                'scopeSpans' => [[
                    'scope' => ['name' => 'altair/observability', 'version' => '1.0'],
                    'spans' => array_map(static fn(Span $s): array => [
                        'traceId' => $s->context->traceId,
                        'spanId' => $s->context->spanId,
                        'parentSpanId' => $s->context->parentSpanId,
                        'name' => $s->name,
                        'kind' => $s->kind->value,
                        'startTimeUnixNano' => (string) $s->startUnixNano,
                        'endTimeUnixNano' => (string) $s->endUnixNano,
                        'attributes' => $s->attributes,
                        'status' => ['code' => $s->status->value, 'message' => $s->statusMessage],
                    ], $spans),
                ]],
            ]],
        ];
    }

    /**
     * @param list<MetricPoint> $metrics
     *
     * @return array<string, mixed>
     */
    private function encodeMetrics(array $metrics): array
    {
        return [
            'resourceMetrics' => [[
                'resource' => ['attributes' => $this->resourceAttributes],
                'scopeMetrics' => [[
                    'scope' => ['name' => 'altair/observability', 'version' => '1.0'],
                    'metrics' => array_map(static fn(MetricPoint $m): array => [
                        'name' => $m->name,
                        'unit' => $m->unit,
                        'description' => $m->description,
                        'kind' => $m->kind->value,
                        'value' => $m->value,
                        'timeUnixNano' => (string) $m->unixNano,
                        'attributes' => $m->attributes,
                    ], $metrics),
                ]],
            ]],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function post(string $url, array $payload): void
    {
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($body === false) {
            $this->logError('Failed to JSON-encode payload for ' . $url);

            return;
        }

        $handle = curl_init($url);
        if ($handle === false) {
            $this->logError('Failed to initialise curl for ' . $url);

            return;
        }

        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => $this->timeoutSeconds,
            CURLOPT_FAILONERROR => false,
        ]);

        $response = curl_exec($handle);
        $status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        curl_close($handle);

        if ($response === false || $status >= 400) {
            $detail = $error !== '' ? $error : 'HTTP ' . $status;
            $this->logError('OTLP export to ' . $url . ' failed: ' . $detail);
        }
    }

    private function logError(string $message): void
    {
        if ($this->errorLogFile === null) {
            return;
        }

        @file_put_contents(
            $this->errorLogFile,
            '[' . date(DATE_ATOM) . \sprintf('] %s%s', $message, PHP_EOL),
            FILE_APPEND | LOCK_EX,
        );
    }
}
