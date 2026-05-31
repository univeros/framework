<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Webhooks\Storage;

/**
 * Immutable record of a single outbound webhook delivery. State transitions
 * produce new copies via the withX() helpers so the store never mutates a
 * delivery in place.
 */
final readonly class Delivery
{
    public function __construct(
        public string $id,
        public string $eventName,
        public string $subscriberUrl,
        public string $payload,
        public string $secretName,
        public string $signerName,
        public DeliveryStatus $status,
        public int $attempts,
        public int $createdAt,
        public ?int $lastAttemptAt = null,
        public ?int $nextAttemptAt = null,
        public ?string $lastResponse = null,
    ) {}

    public static function create(
        string $id,
        string $eventName,
        string $subscriberUrl,
        string $payload,
        string $secretName,
        string $signerName,
        int $createdAt,
    ): self {
        return new self(
            id: $id,
            eventName: $eventName,
            subscriberUrl: $subscriberUrl,
            payload: $payload,
            secretName: $secretName,
            signerName: $signerName,
            status: DeliveryStatus::Pending,
            attempts: 0,
            createdAt: $createdAt,
        );
    }

    public function withStatus(DeliveryStatus $status): self
    {
        return $this->with(status: $status);
    }

    public function withAttempts(int $attempts): self
    {
        return $this->with(attempts: $attempts);
    }

    public function withLastAttemptAt(int $lastAttemptAt): self
    {
        return $this->with(lastAttemptAt: $lastAttemptAt);
    }

    public function withNextAttemptAt(?int $nextAttemptAt): self
    {
        return $this->with(nextAttemptAtProvided: true, nextAttemptAt: $nextAttemptAt);
    }

    public function withLastResponse(?string $lastResponse): self
    {
        return $this->with(lastResponseProvided: true, lastResponse: $lastResponse);
    }

    /**
     * Reset for replay: status back to Pending, attempt counter to zero, and
     * any scheduled next-attempt cleared. Preserves identity + payload.
     */
    public function reset(): self
    {
        return $this->with(
            status: DeliveryStatus::Pending,
            attempts: 0,
            nextAttemptAtProvided: true,
            nextAttemptAt: null,
        );
    }

    private function with(
        ?DeliveryStatus $status = null,
        ?int $attempts = null,
        ?int $lastAttemptAt = null,
        bool $nextAttemptAtProvided = false,
        ?int $nextAttemptAt = null,
        bool $lastResponseProvided = false,
        ?string $lastResponse = null,
    ): self {
        return new self(
            id: $this->id,
            eventName: $this->eventName,
            subscriberUrl: $this->subscriberUrl,
            payload: $this->payload,
            secretName: $this->secretName,
            signerName: $this->signerName,
            status: $status ?? $this->status,
            attempts: $attempts ?? $this->attempts,
            createdAt: $this->createdAt,
            lastAttemptAt: $lastAttemptAt ?? $this->lastAttemptAt,
            nextAttemptAt: $nextAttemptAtProvided ? $nextAttemptAt : $this->nextAttemptAt,
            lastResponse: $lastResponseProvided ? $lastResponse : $this->lastResponse,
        );
    }
}
