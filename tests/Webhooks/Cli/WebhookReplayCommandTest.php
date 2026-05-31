<?php

declare(strict_types=1);

namespace Altair\Tests\Webhooks\Cli;

use Altair\Tests\Webhooks\Dispatcher\RecordingMessageBus;
use Altair\Webhooks\Cli\WebhookReplayCommand;
use Altair\Webhooks\Dispatcher\WebhookDispatcher;
use Altair\Webhooks\Storage\Delivery;
use Altair\Webhooks\Storage\DeliveryStatus;
use Altair\Webhooks\Storage\InMemoryDeliveryStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(WebhookReplayCommand::class)]
final class WebhookReplayCommandTest extends TestCase
{
    public function testReplaysADeadLetteredDeliveryByFullId(): void
    {
        $store = new InMemoryDeliveryStore();
        $store->record($this->deadLettered('01HZZZAAAA0000000000000001'));

        $bus = new RecordingMessageBus();
        $tester = new CommandTester(new WebhookReplayCommand($store, new WebhookDispatcher($bus, $store)));

        $exit = $tester->execute(['delivery-id' => '01HZZZAAAA0000000000000001']);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertSame(DeliveryStatus::Pending, $store->findById('01HZZZAAAA0000000000000001')?->status);
        self::assertSame('01HZZZAAAA0000000000000001', $bus->lastWebhookMessage()?->deliveryId);
    }

    public function testReplaysByUnambiguousPrefix(): void
    {
        $store = new InMemoryDeliveryStore();
        $store->record($this->deadLettered('01HZZZAAAA0000000000000001'));

        $bus = new RecordingMessageBus();
        $tester = new CommandTester(new WebhookReplayCommand($store, new WebhookDispatcher($bus, $store)));

        $exit = $tester->execute(['delivery-id' => '01HZZZAAAA']);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertSame('01HZZZAAAA0000000000000001', $bus->lastWebhookMessage()?->deliveryId);
    }

    public function testFailsForUnknownDelivery(): void
    {
        $store = new InMemoryDeliveryStore();
        $tester = new CommandTester(new WebhookReplayCommand($store, new WebhookDispatcher(new RecordingMessageBus(), $store)));

        $exit = $tester->execute(['delivery-id' => 'nope']);

        self::assertSame(Command::FAILURE, $exit);
        self::assertStringContainsString('No delivery matching', $tester->getDisplay());
    }

    private function deadLettered(string $id): Delivery
    {
        return Delivery::create(
            id: $id,
            eventName: 'order.created',
            subscriberUrl: 'https://example.test/hook',
            payload: '{"id":"order_1"}',
            secretName: 'partner-x',
            signerName: 'hmac-sha256',
            createdAt: 1_700_000_000,
        )->withStatus(DeliveryStatus::DeadLettered)->withAttempts(5);
    }
}
