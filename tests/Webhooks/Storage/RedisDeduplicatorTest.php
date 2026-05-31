<?php

declare(strict_types=1);

namespace Altair\Tests\Webhooks\Storage;

use Altair\Webhooks\Storage\RedisDeduplicator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Redis;

#[CoversClass(RedisDeduplicator::class)]
#[RequiresPhpExtension('redis')]
#[Group('redis')]
final class RedisDeduplicatorTest extends TestCase
{
    private ?Redis $redis = null;

    protected function setUp(): void
    {
        $host = getenv('REDIS_HOST');
        if ($host === false || $host === '') {
            self::markTestSkipped('REDIS_HOST not set.');
        }

        $redis = new Redis();
        $redis->connect($host, (int) (getenv('REDIS_PORT') ?: 6379));
        $this->redis = $redis;
        $redis->flushDB();
    }

    protected function tearDown(): void
    {
        $this->redis?->flushDB();
    }

    public function testClaimSucceedsOnceThenFails(): void
    {
        $dedupe = new RedisDeduplicator($this->redis());

        self::assertTrue($dedupe->claim('evt_1', 60));
        self::assertFalse($dedupe->claim('evt_1', 60));
    }

    public function testReleaseAllowsReclaim(): void
    {
        $dedupe = new RedisDeduplicator($this->redis());
        $dedupe->claim('evt_1', 60);

        $dedupe->release('evt_1');

        self::assertTrue($dedupe->claim('evt_1', 60));
    }

    private function redis(): Redis
    {
        self::assertInstanceOf(Redis::class, $this->redis);

        return $this->redis;
    }
}
