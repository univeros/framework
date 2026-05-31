<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Idempotency\Storage;

use Altair\Idempotency\Exception\IdempotencyException;
use JsonException;

/**
 * Captured HTTP response under an idempotency key, plus the request-body
 * hash that produced it. Carries everything the middleware needs to
 * replay an identical response — and to detect when a future request
 * reuses the key with a different payload.
 *
 * `inProgress: true` marks an in-flight claim that has not yet been
 * completed. Adapters use it to coordinate concurrent identical
 * requests so only one execution happens per key.
 *
 * @phpstan-type HeaderMap array<string, list<string>>
 */
final readonly class StoredResponse
{
    /**
     * @param HeaderMap $headers
     */
    public function __construct(
        public string $requestHash,
        public int $status,
        public array $headers,
        public string $body,
        public bool $inProgress,
        public int $createdAt,
    ) {}

    /**
     * Marker for a key that has been claimed but whose response has not
     * yet been captured. Body / status are empty placeholders until
     * {@see self::completed()} replaces the row.
     */
    public static function inProgress(string $requestHash, int $createdAt): self
    {
        return new self(
            requestHash: $requestHash,
            status: 0,
            headers: [],
            body: '',
            inProgress: true,
            createdAt: $createdAt,
        );
    }

    /**
     * @param HeaderMap $headers
     */
    public static function completed(
        string $requestHash,
        int $status,
        array $headers,
        string $body,
        int $createdAt,
    ): self {
        return new self(
            requestHash: $requestHash,
            status: $status,
            headers: $headers,
            body: $body,
            inProgress: false,
            createdAt: $createdAt,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'request_hash' => $this->requestHash,
            'status' => $this->status,
            'headers' => $this->headers,
            'body' => $this->body,
            'in_progress' => $this->inProgress,
            'created_at' => $this->createdAt,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        foreach (['request_hash', 'status', 'headers', 'body', 'in_progress', 'created_at'] as $required) {
            if (!\array_key_exists($required, $data)) {
                throw new IdempotencyException(\sprintf('StoredResponse is missing required field "%s".', $required));
            }
        }

        $headers = $data['headers'];
        if (!\is_array($headers)) {
            throw new IdempotencyException('StoredResponse "headers" must be an array.');
        }

        /** @var array<string, list<string>> $normalisedHeaders */
        $normalisedHeaders = [];
        foreach ($headers as $name => $values) {
            if (!\is_string($name)) {
                continue;
            }

            if (!\is_array($values)) {
                continue;
            }

            $normalisedHeaders[$name] = array_values(array_map(strval(...), $values));
        }

        return new self(
            requestHash: (string) $data['request_hash'],
            status: (int) $data['status'],
            headers: $normalisedHeaders,
            body: (string) $data['body'],
            inProgress: (bool) $data['in_progress'],
            createdAt: (int) $data['created_at'],
        );
    }

    public function toJson(): string
    {
        try {
            return json_encode(
                $this->toArray(),
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            );
        } catch (JsonException $jsonException) {
            throw new IdempotencyException('StoredResponse is not JSON-encodable: ' . $jsonException->getMessage(), 0, $jsonException);
        }
    }

    public static function fromJson(string $json): self
    {
        try {
            /** @var mixed $decoded */
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw new IdempotencyException('StoredResponse JSON is malformed: ' . $jsonException->getMessage(), 0, $jsonException);
        }

        if (!\is_array($decoded)) {
            throw new IdempotencyException('StoredResponse JSON must decode to a map.');
        }

        /** @var array<string, mixed> $decoded */
        return self::fromArray($decoded);
    }
}
