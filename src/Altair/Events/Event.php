<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Events;

use Altair\Events\Exception\InvalidArgumentException;
use DateTimeImmutable;
use DateTimeInterface;
use JsonException;
use Symfony\Component\Uid\Ulid;

/**
 * One immutable line in `.altair/events.jsonl`.
 *
 * Construct via the named-constructor factory methods so each kind's
 * required shape stays type-checked at the call site:
 *
 *     Event::scaffold(command: ..., changes: ..., durationMs: ...)
 *     Event::migration(...)
 *
 * The Recorder appends the JSON form; the Reader hydrates back through
 * {@see self::fromArray()}. Fields are flat for jq-friendliness.
 *
 * @phpstan-type EventArray array{
 *     id: string,
 *     timestamp: string,
 *     actor: string,
 *     user?: ?string,
 *     client?: ?string,
 *     command: string,
 *     kind: string,
 *     status: string,
 *     duration_ms: int,
 *     changes?: array<string, mixed>,
 *     error?: ?string,
 *     extra?: array<string, mixed>
 * }
 */
final readonly class Event
{
    /**
     * @param array<string, mixed> $extra Kind-specific fields that don't fit the common shape.
     */
    public function __construct(
        public string $id,
        public DateTimeImmutable $timestamp,
        public Actor $actor,
        public string $command,
        public EventKind $kind,
        public EventStatus $status,
        public int $durationMs,
        public ?string $user = null,
        public ?string $client = null,
        public ?Changes $changes = null,
        public ?string $error = null,
        public array $extra = [],
    ) {
        if ($id === '') {
            throw new InvalidArgumentException('Event id must not be empty.');
        }
        if ($command === '') {
            throw new InvalidArgumentException('Event command must not be empty.');
        }
        if ($durationMs < 0) {
            throw new InvalidArgumentException('Event duration must be non-negative.');
        }
        if ($status === EventStatus::Fail && ($error === null || $error === '')) {
            throw new InvalidArgumentException('Failed events must carry a non-empty error description.');
        }
    }

    /**
     * Common-case factory: stamp an event with a fresh ULID and the current UTC instant.
     */
    public static function create(
        Actor $actor,
        string $command,
        EventKind $kind,
        EventStatus $status,
        int $durationMs,
        ?string $user = null,
        ?string $client = null,
        ?Changes $changes = null,
        ?string $error = null,
        array $extra = [],
    ): self {
        return new self(
            id: (new Ulid())->toBase32(),
            timestamp: new DateTimeImmutable('now'),
            actor: $actor,
            command: $command,
            kind: $kind,
            status: $status,
            durationMs: max(0, $durationMs),
            user: $user,
            client: $client,
            changes: $changes,
            error: $error,
            extra: $extra,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $out = [
            'id' => $this->id,
            'timestamp' => $this->timestamp->format(DateTimeInterface::RFC3339_EXTENDED),
            'actor' => $this->actor->value,
            'user' => $this->user,
            'client' => $this->client,
            'command' => $this->command,
            'kind' => $this->kind->value,
            'status' => $this->status->value,
            'duration_ms' => $this->durationMs,
        ];

        if ($this->changes !== null) {
            $out['changes'] = $this->changes->toArray();
        }
        if ($this->error !== null) {
            $out['error'] = $this->error;
        }
        if ($this->extra !== []) {
            $out['extra'] = $this->extra;
        }

        return $out;
    }

    /**
     * One JSON line, no trailing newline (the storage layer adds it).
     */
    public function toJsonLine(): string
    {
        try {
            return json_encode($this->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $e) {
            throw new InvalidArgumentException('Event is not JSON-encodable: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        foreach (['id', 'timestamp', 'actor', 'command', 'kind', 'status', 'duration_ms'] as $required) {
            if (!\array_key_exists($required, $data)) {
                throw new InvalidArgumentException(\sprintf("Missing required field '%s' on event.", $required));
            }
        }

        $timestamp = $data['timestamp'];
        if (!\is_string($timestamp) || ($parsed = DateTimeImmutable::createFromFormat(DateTimeInterface::RFC3339_EXTENDED, $timestamp)) === false) {
            // Fallback: RFC3339 without microseconds.
            $parsed = $timestamp !== '' && \is_string($timestamp)
                ? (DateTimeImmutable::createFromFormat(DateTimeInterface::RFC3339, $timestamp) ?: null)
                : null;
            if ($parsed === null) {
                throw new InvalidArgumentException(\sprintf("Invalid timestamp '%s'.", (string) $timestamp));
            }
        }

        $changes = null;
        if (isset($data['changes']) && \is_array($data['changes'])) {
            $changes = Changes::fromArray($data['changes']);
        }

        $extra = [];
        if (isset($data['extra']) && \is_array($data['extra'])) {
            $extra = $data['extra'];
        }

        return new self(
            id: (string) $data['id'],
            timestamp: $parsed,
            actor: Actor::from((string) $data['actor']),
            command: (string) $data['command'],
            kind: EventKind::fromString((string) $data['kind']),
            status: EventStatus::from((string) $data['status']),
            durationMs: (int) $data['duration_ms'],
            user: isset($data['user']) && \is_string($data['user']) ? $data['user'] : null,
            client: isset($data['client']) && \is_string($data['client']) ? $data['client'] : null,
            changes: $changes,
            error: isset($data['error']) && \is_string($data['error']) ? $data['error'] : null,
            extra: $extra,
        );
    }
}
