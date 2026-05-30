<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Scaffold\Cli;

use JsonException;
use RuntimeException;

/**
 * Structured outcome of `openapi:roundtrip`. Same agent-facing-contract
 * principle as {@see ImportReceipt}: shape is the JSON wire format,
 * byte-stable for the same input.
 */
final readonly class RoundtripReceipt
{
    /**
     * @param list<RoundtripDifference> $differences
     */
    public function __construct(
        public bool $clean,
        public string $input,
        public int $operationsCompared,
        public array $differences,
        public ?string $error,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'clean' => $this->clean,
            'input' => $this->input,
            'operations_compared' => $this->operationsCompared,
            'differences' => array_map(static fn(RoundtripDifference $d): array => $d->toArray(), $this->differences),
            'error' => $this->error,
        ];
    }

    public function toJson(): string
    {
        try {
            return json_encode(
                $this->toArray(),
                JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            );
        } catch (JsonException $jsonException) {
            throw new RuntimeException('RoundtripReceipt is not JSON-encodable: ' . $jsonException->getMessage(), 0, $jsonException);
        }
    }
}
