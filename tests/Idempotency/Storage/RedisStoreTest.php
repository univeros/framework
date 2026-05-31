<?php

declare(strict_types=1);

namespace Altair\Tests\Idempotency\Storage;

use Altair\Idempotency\Storage\RedisStore;
use Altair\Idempotency\Storage\StoredResponse;
use PHPUnit\Framework\TestCase;
use Redis;

final class RedisStoreTest extends TestCase
{
    private ?Redis $redis = null;

    protected function setUp(): void
    {
        if (!\extension_loaded('redis')) {
            self::markTestSkipped('ext-redis is not loaded; skipping RedisStore tests.');
        }

        $host = getenv('REDIS_HOST') ?: '127.0.0.1';
        $port = (int) (getenv('REDIS_PORT') ?: 6379);

        $redis = new Redis();
        try {
            $connected = @$redis->connect($host, $port, 0.5);
        } catch (\Throwable) {
            $connected = false;
        }

        if (!$connected) {
            self::markTestSkipped(sprintf('Cannot reach Redis at %s:%d; skipping RedisStore tests.', $host, $port));
        }

        // Isolate test data — use a dedicated db when possible.
        try {
            $redis->select(15);
            $redis->flushDB();
        } catch (\Throwable) {
            // continue with whatever db; flushDB inside the test prefix will still isolate
        }

        $this->redis = $redis;
    }

    protected function tearDown(): void
    {
        if ($this->redis instanceof \Redis) {
            try {
                $this->redis->flushDB();
                $this->redis->close();
            } catch (\Throwable) {
                // best-effort cleanup
            }
        }
    }

    public function testClaimFreshKey(): void
    {
        \assert($this->redis instanceof Redis);
        $store = new RedisStore($this->redis, keyPrefix: 'test.idem.');

        self::assertNull($store->claim('k1', 'hash', 60));
    }

    public function testConcurrentClaimReturnsInProgressEntry(): void
    {
        \assert($this->redis instanceof Redis);
        $store = new RedisStore($this->redis, keyPrefix: 'test.idem.');
        $store->claim('k1', 'hash', 60);

        $second = $store->claim('k1', 'hash', 60);

        self::assertInstanceOf(StoredResponse::class, $second);
        self::assertTrue($second->inProgress);
    }

    public function testCompleteThenGet(): void
    {
        \assert($this->redis instanceof Redis);
        $store = new RedisStore($this->redis, keyPrefix: 'test.idem.');
        $store->claim('k1', 'hash', 60);

        $store->complete('k1', StoredResponse::completed('hash', 201, ['Location' => ['/things/1']], '{"id":1}', 0), 60);

        $fetched = $store->get('k1');
        self::assertInstanceOf(StoredResponse::class, $fetched);
        self::assertSame(201, $fetched->status);
        self::assertSame('{"id":1}', $fetched->body);
        self::assertSame(['Location' => ['/things/1']], $fetched->headers);
    }

    public function testReleaseDropsClaim(): void
    {
        \assert($this->redis instanceof Redis);
        $store = new RedisStore($this->redis, keyPrefix: 'test.idem.');
        $store->claim('k1', 'hash', 60);

        $store->release('k1');

        self::assertNull($store->get('k1'));
    }
}
