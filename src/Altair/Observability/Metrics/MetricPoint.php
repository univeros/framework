<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Observability\Metrics;

/**
 * One recorded metric observation: name, kind, value, attributes, and the
 * absolute Unix-nano timestamp. Histogram samples are recorded one point at a
 * time and an aggregator collapses them at export time.
 */
final readonly class MetricPoint
{
    /**
     * @param array<string, scalar|null|list<scalar|null>> $attributes
     */
    public function __construct(
        public string $name,
        public MetricKind $kind,
        public float $value,
        public int $unixNano,
        public array $attributes = [],
        public ?string $unit = null,
        public ?string $description = null,
    ) {}

    /**
     * @param array<string, mixed> $row a row as produced by {@see self::toArray()} or the JSONL exporter
     */
    public static function fromArray(array $row): self
    {
        /** @var array<string, scalar|null|list<scalar|null>> $attributes */
        $attributes = \is_array($row['attributes'] ?? null) ? $row['attributes'] : [];

        return new self(
            (string) ($row['name'] ?? ''),
            MetricKind::from((string) ($row['kind'] ?? MetricKind::Counter->value)),
            (float) ($row['value'] ?? 0),
            (int) ($row['unix_nano'] ?? 0),
            $attributes,
            \is_string($row['unit'] ?? null) ? $row['unit'] : null,
            \is_string($row['description'] ?? null) ? $row['description'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'kind' => $this->kind->value,
            'value' => $this->value,
            'unix_nano' => $this->unixNano,
            'attributes' => $this->attributes,
            'unit' => $this->unit,
            'description' => $this->description,
        ];
    }
}
