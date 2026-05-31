<?php

declare(strict_types=1);

namespace Altair\Tests\Webhooks\Storage;

use Altair\Webhooks\Storage\Delivery;
use Altair\Webhooks\Storage\DeliveryStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Delivery::class)]
final class DeliveryTest extends TestCase
{
    public function testCreateStartsPendingWithZeroAttempts(): void
    {
        $delivery = $this->delivery();

        self::assertSame('dlv_1', $delivery->id);
        self::assertSame(DeliveryStatus::Pending, $delivery->status);
        self::assertSame(0, $delivery->attempts);
        self::assertNull($delivery->lastAttemptAt);
        self::assertNull($delivery->nextAttemptAt);
        self::assertNull($delivery->lastResponse);
    }

    public function testWithStatusReturnsNewCopy(): void
    {
        $delivery = $this->delivery();

        $delivered = $delivery->withStatus(DeliveryStatus::Delivered);

        self::assertSame(DeliveryStatus::Pending, $delivery->status, 'original is untouched');
        self::assertSame(DeliveryStatus::Delivered, $delivered->status);
    }

    public function testWithAttemptsAndTimestamps(): void
    {
        $delivery = $this->delivery()
            ->withAttempts(2)
            ->withLastAttemptAt(1_700_000_100)
            ->withNextAttemptAt(1_700_000_160)
            ->withLastResponse('502 Bad Gateway');

        self::assertSame(2, $delivery->attempts);
        self::assertSame(1_700_000_100, $delivery->lastAttemptAt);
        self::assertSame(1_700_000_160, $delivery->nextAttemptAt);
        self::assertSame('502 Bad Gateway', $delivery->lastResponse);
    }

    public function testWithNextAttemptAtCanClearToNull(): void
    {
        $delivery = $this->delivery()->withNextAttemptAt(1_700_000_160)->withNextAttemptAt(null);

        self::assertNull($delivery->nextAttemptAt);
    }

    public function testResetRestoresPendingAndZeroAttempts(): void
    {
        $delivery = $this->delivery()
            ->withStatus(DeliveryStatus::DeadLettered)
            ->withAttempts(5)
            ->withNextAttemptAt(1_700_000_160);

        $reset = $delivery->reset();

        self::assertSame(DeliveryStatus::Pending, $reset->status);
        self::assertSame(0, $reset->attempts);
        self::assertNull($reset->nextAttemptAt);
        self::assertSame('dlv_1', $reset->id, 'identity preserved');
        self::assertSame($delivery->payload, $reset->payload, 'payload preserved');
    }

    private function delivery(): Delivery
    {
        return Delivery::create(
            id: 'dlv_1',
            eventName: 'order.created',
            subscriberUrl: 'https://example.test/hook',
            payload: '{"id":"order_1"}',
            secretName: 'partner-x',
            signerName: 'hmac-sha256',
            createdAt: 1_700_000_000,
        );
    }
}
