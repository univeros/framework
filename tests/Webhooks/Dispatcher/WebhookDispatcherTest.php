<?php

declare(strict_types=1);

namespace Altair\Tests\Webhooks\Dispatcher;

use Altair\Webhooks\Dispatcher\WebhookDispatcher;
use Altair\Webhooks\Dispatcher\WebhookMessage;
use Altair\Webhooks\Storage\Delivery;
use Altair\Webhooks\Storage\DeliveryStatus;
use Altair\Webhooks\Storage\InMemoryDeliveryStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(WebhookDispatcher::class)]
#[CoversClass(WebhookMessage::class)]
final class WebhookDispatcherTest extends TestCase
{
    public function testDispatchRecordsPendingDeliveryAndDispatchesMessage(): void
    {
        $store = new InMemoryDeliveryStore();
        $bus = new RecordingMessageBus();
        $dispatcher = new WebhookDispatcher($bus, $store);

        $delivery = $dispatcher->dispatch(
            eventName: 'order.created',
            payload: '{"id":"order_1"}',
            subscriberUrl: 'https://example.test/hook',
            secretName: 'partner-x',
        );

        self::assertSame(DeliveryStatus::Pending, $delivery->status);
        self::assertSame('hmac-sha256', $delivery->signerName);
        self::assertEquals($delivery, $store->findById($delivery->id));

        $message = $bus->lastWebhookMessage();
        self::assertInstanceOf(WebhookMessage::class, $message);
        self::assertSame($delivery->id, $message->deliveryId);
        self::assertSame('order.created', $message->eventName);
        self::assertSame('{"id":"order_1"}', $message->payload);
    }

    public function testDispatchEncodesArrayPayloadToJson(): void
    {
        $store = new InMemoryDeliveryStore();
        $dispatcher = new WebhookDispatcher(new RecordingMessageBus(), $store);

        $delivery = $dispatcher->dispatch(
            eventName: 'order.created',
            payload: ['id' => 'order_1', 'total' => 42],
            subscriberUrl: 'https://example.test/hook',
            secretName: 'partner-x',
        );

        self::assertSame('{"id":"order_1","total":42}', $delivery->payload);
    }

    public function testDispatchHonoursExplicitSigner(): void
    {
        $dispatcher = new WebhookDispatcher(new RecordingMessageBus(), new InMemoryDeliveryStore());

        $delivery = $dispatcher->dispatch(
            eventName: 'order.created',
            payload: '{}',
            subscriberUrl: 'https://example.test/hook',
            secretName: 'partner-x',
            signerName: 'ed25519',
        );

        self::assertSame('ed25519', $delivery->signerName);
    }

    public function testRedispatchResetsAndRedispatches(): void
    {
        $store = new InMemoryDeliveryStore();
        $bus = new RecordingMessageBus();
        $dispatcher = new WebhookDispatcher($bus, $store);

        $deadLettered = Delivery::create(
            id: 'dlv_1',
            eventName: 'order.created',
            subscriberUrl: 'https://example.test/hook',
            payload: '{"id":"order_1"}',
            secretName: 'partner-x',
            signerName: 'hmac-sha256',
            createdAt: 1_700_000_000,
        )->withStatus(DeliveryStatus::DeadLettered)->withAttempts(5);
        $store->record($deadLettered);

        $reset = $dispatcher->redispatch($deadLettered);

        self::assertSame(DeliveryStatus::Pending, $reset->status);
        self::assertSame(0, $reset->attempts);
        self::assertSame(DeliveryStatus::Pending, $store->findById('dlv_1')?->status);
        self::assertSame('dlv_1', $bus->lastWebhookMessage()?->deliveryId);
    }
}
