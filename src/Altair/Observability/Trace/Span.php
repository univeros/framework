<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Observability\Trace;

/**
 * One completed span: name, timing, status, kind, and arbitrary attributes.
 *
 * Times are absolute Unix nanoseconds (the OTLP wire format), so a host's
 * exported spans align cleanly with traces collected from other services.
 * Attribute values are scalars, scalar lists, or nulls — the OTLP attribute
 * value variants.
 */
final readonly class Span
{
    /**
     * @param array<string, scalar|null|list<scalar|null>> $attributes
     */
    public function __construct(
        public SpanContext $context,
        public string $name,
        public SpanKind $kind,
        public int $startUnixNano,
        public int $endUnixNano,
        public SpanStatus $status,
        public array $attributes = [],
        public ?string $statusMessage = null,
    ) {}

    public function durationNs(): int
    {
        return max(0, $this->endUnixNano - $this->startUnixNano);
    }

    public function durationMs(): float
    {
        return $this->durationNs() / 1_000_000;
    }

    /**
     * @param array<string, mixed> $row a row as produced by {@see self::toArray()} or the JSONL exporter
     */
    public static function fromArray(array $row): self
    {
        $statusRow = $row['status'] ?? [];
        $statusCode = \is_array($statusRow) ? (int) ($statusRow['code'] ?? 0) : 0;

        /** @var array<string, scalar|null|list<scalar|null>> $attributes */
        $attributes = \is_array($row['attributes'] ?? null) ? $row['attributes'] : [];

        return new self(
            new SpanContext(
                (string) ($row['trace_id'] ?? ''),
                (string) ($row['span_id'] ?? ''),
                isset($row['parent_span_id']) && \is_string($row['parent_span_id']) ? $row['parent_span_id'] : null,
            ),
            (string) ($row['name'] ?? ''),
            SpanKind::from((int) ($row['kind'] ?? SpanKind::Internal->value)),
            (int) ($row['start_unix_nano'] ?? 0),
            (int) ($row['end_unix_nano'] ?? 0),
            SpanStatus::from($statusCode),
            $attributes,
            \is_array($statusRow) && \is_string($statusRow['message'] ?? null) ? $statusRow['message'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'trace_id' => $this->context->traceId,
            'span_id' => $this->context->spanId,
            'parent_span_id' => $this->context->parentSpanId,
            'name' => $this->name,
            'kind' => $this->kind->value,
            'start_unix_nano' => $this->startUnixNano,
            'end_unix_nano' => $this->endUnixNano,
            'duration_ms' => $this->durationMs(),
            'status' => [
                'code' => $this->status->value,
                'message' => $this->statusMessage,
            ],
            'attributes' => $this->attributes,
        ];
    }
}
