<?php

declare(strict_types=1);

namespace Altair\Tests\Webhooks\Storage;

use Altair\Webhooks\Storage\Delivery;
use Altair\Webhooks\Storage\DeliveryStatus;
use Altair\Webhooks\Storage\RedisDeliveryStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Redis;

#[CoversClass(RedisDeliveryStore::class)]
#[RequiresPhpExtension('redis')]
#[Group('redis')]
final class RedisDeliveryStoreTest extends TestCase
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

    public function testRecordAndFindByIdRoundTrip(): void
    {
        $store = new RedisDeliveryStore($this->redis());
        $delivery = $this->delivery('dlv_1', 1_700_000_000);

        $store->record($delivery);

        self::assertEquals($delivery, $store->findById('dlv_1'));
    }

    public function testFindByIdReturnsNullWhenMissing(): void
    {
        self::assertNull((new RedisDeliveryStore($this->redis()))->findById('missing'));
    }

    public function testFindFailedReturnsDeadLetteredOldestFirst(): void
    {
        $store = new RedisDeliveryStore($this->redis());
        $store->record($this->delivery('dlv_new', 3_000)->withStatus(DeliveryStatus::DeadLettered));
        $store->record($this->delivery('dlv_old', 1_000)->withStatus(DeliveryStatus::DeadLettered));
        $store->record($this->delivery('dlv_ok', 2_000)->withStatus(DeliveryStatus::Delivered));

        $failed = $store->findFailed();

        self::assertCount(2, $failed);
        self::assertSame('dlv_old', $failed[0]->id);
        self::assertSame('dlv_new', $failed[1]->id);
    }

    public function testUpdateOutOfDeadLetterRemovesFromFailedIndex(): void
    {
        $store = new RedisDeliveryStore($this->redis());
        $store->record($this->delivery('dlv_1', 1_000)->withStatus(DeliveryStatus::DeadLettered));

        $store->update($this->delivery('dlv_1', 1_000)->withStatus(DeliveryStatus::Delivered));

        self::assertCount(0, $store->findFailed());
    }

    private function delivery(string $id, int $createdAt): Delivery
    {
        return Delivery::create(
            id: $id,
            eventName: 'order.created',
            subscriberUrl: 'https://example.test/hook',
            payload: '{"id":"order_1"}',
            secretName: 'partner-x',
            signerName: 'hmac-sha256',
            createdAt: $createdAt,
        );
    }

    private function redis(): Redis
    {
        self::assertInstanceOf(Redis::class, $this->redis);

        return $this->redis;
    }
}
