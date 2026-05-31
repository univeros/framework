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
use Altair\Idempotency\Exception\IdempotencyException;

/**
 * Single-host idempotency store backed by APCu's shared memory cache.
 *
 * `apcu_add` is the atomic claim primitive: it inserts a key only when
 * absent, returning `false` when the key already exists. That is what
 * makes concurrent claims for the same key safe.
 *
 * The key namespace is configurable so multiple applications sharing
 * one APCu instance do not collide; default `altair.idem.`.
 *
 * Throws {@see IdempotencyException} at construction time when the
 * APCu extension is not available — the store is unusable in that
 * environment and silently degrading would mask production bugs.
 */
final readonly class ApcuStore implements IdempotencyStoreInterface
{
    public function __construct(private string $keyPrefix = 'altair.idem.')
    {
        if (!\function_exists('apcu_add')) {
            throw new IdempotencyException('ApcuStore requires the apcu extension; install or enable ext-apcu, or pick a different IdempotencyStore implementation.');
        }
    }

    public function claim(string $key, string $requestHash, int $ttlSeconds): ?StoredResponse
    {
        $fullKey = $this->qualify($key);
        $now = time();
        $entry = StoredResponse::inProgress($requestHash, $now);

        if (apcu_add($fullKey, $entry->toJson(), $ttlSeconds)) {
            return null;
        }

        return $this->fetch($fullKey);
    }

    public function complete(string $key, StoredResponse $response, int $ttlSeconds): void
    {
        $fullKey = $this->qualify($key);
        $stored = apcu_store($fullKey, $response->toJson(), $ttlSeconds);
        if ($stored !== true) {
            throw new IdempotencyException(\sprintf("ApcuStore::complete() failed to write key '%s'.", $fullKey));
        }
    }

    public function release(string $key): void
    {
        apcu_delete($this->qualify($key));
    }

    public function get(string $key): ?StoredResponse
    {
        return $this->fetch($this->qualify($key));
    }

    private function fetch(string $fullKey): ?StoredResponse
    {
        $success = false;
        $raw = apcu_fetch($fullKey, $success);
        if (!$success || !\is_string($raw)) {
            return null;
        }

        return StoredResponse::fromJson($raw);
    }

    private function qualify(string $key): string
    {
        return $this->keyPrefix . $key;
    }
}
