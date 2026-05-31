<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Idempotency\Storage;

use Altair\Idempotency\Contracts\IdempotencyStoreInterface;

/**
 * Process-local store. Suitable for tests and single-worker scripts;
 * never for production HTTP. Entries live in a plain PHP array and are
 * subject to a soft TTL: `get()` returns `null` for expired entries
 * and lazily removes them.
 *
 * The clock is injectable so tests can advance time deterministically.
 */
final class InMemoryStore implements IdempotencyStoreInterface
{
    /** @var array<string, array{response: StoredResponse, expires_at: int}> */
    private array $entries = [];

    /** @var callable(): int */
    private $clock;

    /**
     * @param ?callable(): int $clock Returns Unix seconds. Defaults to `time()`.
     */
    public function __construct(?callable $clock = null)
    {
        $this->clock = $clock ?? time(...);
    }

    public function claim(string $key, string $requestHash, int $ttlSeconds): ?StoredResponse
    {
        $existing = $this->get($key);
        if ($existing instanceof StoredResponse) {
            return $existing;
        }

        $now = ($this->clock)();
        $this->entries[$key] = [
            'response' => StoredResponse::inProgress($requestHash, $now),
            'expires_at' => $now + $ttlSeconds,
        ];

        return null;
    }

    public function complete(string $key, StoredResponse $response, int $ttlSeconds): void
    {
        $now = ($this->clock)();
        $this->entries[$key] = [
            'response' => $response,
            'expires_at' => $now + $ttlSeconds,
        ];
    }

    public function release(string $key): void
    {
        unset($this->entries[$key]);
    }

    public function get(string $key): ?StoredResponse
    {
        if (!isset($this->entries[$key])) {
            return null;
        }

        $entry = $this->entries[$key];
        if ($entry['expires_at'] <= ($this->clock)()) {
            unset($this->entries[$key]);

            return null;
        }

        return $entry['response'];
    }
}
