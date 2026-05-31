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
use Redis;
use RedisException;

/**
 * Multi-host idempotency store backed by a Redis instance.
 *
 * The atomic claim primitive is `SET key value NX EX ttl`: insert only
 * when absent, with a TTL applied in the same round-trip. That makes
 * concurrent claims for the same key safe across an arbitrary number
 * of workers and servers.
 *
 * The key namespace is configurable; default `altair.idem.`.
 *
 * The constructor accepts a pre-configured \Redis client. Connection
 * lifecycle (pooling, reconnection, authentication) is the host
 * application's responsibility — this class only consumes the client.
 */
final readonly class RedisStore implements IdempotencyStoreInterface
{
    public function __construct(
        private Redis $redis,
        private string $keyPrefix = 'altair.idem.',
    ) {}

    public function claim(string $key, string $requestHash, int $ttlSeconds): ?StoredResponse
    {
        $fullKey = $this->qualify($key);
        $entry = StoredResponse::inProgress($requestHash, time());

        try {
            $set = $this->redis->set($fullKey, $entry->toJson(), ['NX', 'EX' => $ttlSeconds]);
        } catch (RedisException $redisException) {
            throw new IdempotencyException('RedisStore::claim() failed: ' . $redisException->getMessage(), 0, $redisException);
        }

        if ($set === true) {
            return null;
        }

        return $this->fetch($fullKey);
    }

    public function complete(string $key, StoredResponse $response, int $ttlSeconds): void
    {
        $fullKey = $this->qualify($key);

        try {
            $set = $this->redis->set($fullKey, $response->toJson(), ['EX' => $ttlSeconds]);
        } catch (RedisException $redisException) {
            throw new IdempotencyException('RedisStore::complete() failed: ' . $redisException->getMessage(), 0, $redisException);
        }

        if ($set !== true) {
            throw new IdempotencyException(\sprintf("RedisStore::complete() failed to write key '%s'.", $fullKey));
        }
    }

    public function release(string $key): void
    {
        try {
            $this->redis->del($this->qualify($key));
        } catch (RedisException $redisException) {
            throw new IdempotencyException('RedisStore::release() failed: ' . $redisException->getMessage(), 0, $redisException);
        }
    }

    public function get(string $key): ?StoredResponse
    {
        return $this->fetch($this->qualify($key));
    }

    private function fetch(string $fullKey): ?StoredResponse
    {
        try {
            $raw = $this->redis->get($fullKey);
        } catch (RedisException $redisException) {
            throw new IdempotencyException('RedisStore::get() failed: ' . $redisException->getMessage(), 0, $redisException);
        }

        if (!\is_string($raw)) {
            return null;
        }

        return StoredResponse::fromJson($raw);
    }

    private function qualify(string $key): string
    {
        return $this->keyPrefix . $key;
    }
}
