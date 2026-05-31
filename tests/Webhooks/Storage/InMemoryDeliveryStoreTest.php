<?php

declare(strict_types=1);

namespace Altair\Tests\Webhooks\Storage;

use Altair\Webhooks\Storage\Delivery;
use Altair\Webhooks\Storage\DeliveryStatus;
use Altair\Webhooks\Storage\InMemoryDeliveryStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InMemoryDeliveryStore::class)]
final class InMemoryDeliveryStoreTest extends TestCase
{
    public function testRecordAndFindById(): void
    {
        $store = new InMemoryDeliveryStore();
        $delivery = $this->delivery('dlv_1', 1_700_000_000);

        $store->record($delivery);

        self::assertEquals($delivery, $store->findById('dlv_1'));
    }

    public function testFindByIdReturnsNullWhenMissing(): void
    {
        self::assertNull((new InMemoryDeliveryStore())->findById('missing'));
    }

    public function testUpdateOverwritesExisting(): void
    {
        $store = new InMemoryDeliveryStore();
        $store->record($this->delivery('dlv_1', 1_700_000_000));

        $store->update($this->delivery('dlv_1', 1_700_000_000)->withStatus(DeliveryStatus::Delivered));

        self::assertSame(DeliveryStatus::Delivered, $store->findById('dlv_1')?->status);
    }

    public function testFindFailedReturnsOnlyDeadLetteredOldestFirst(): void
    {
        $store = new InMemoryDeliveryStore();
        $store->record($this->delivery('dlv_new', 3_000)->withStatus(DeliveryStatus::DeadLettered));
        $store->record($this->delivery('dlv_old', 1_000)->withStatus(DeliveryStatus::DeadLettered));
        $store->record($this->delivery('dlv_ok', 2_000)->withStatus(DeliveryStatus::Delivered));

        $failed = $store->findFailed();

        self::assertCount(2, $failed);
        self::assertSame('dlv_old', $failed[0]->id);
        self::assertSame('dlv_new', $failed[1]->id);
    }

    public function testFindFailedHonoursLimit(): void
    {
        $store = new InMemoryDeliveryStore();
        $store->record($this->delivery('dlv_1', 1_000)->withStatus(DeliveryStatus::DeadLettered));
        $store->record($this->delivery('dlv_2', 2_000)->withStatus(DeliveryStatus::DeadLettered));

        self::assertCount(1, $store->findFailed(1));
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
}
