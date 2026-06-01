<?php

declare(strict_types=1);

namespace Altair\Tests\Webhooks\Cli;

use Altair\Webhooks\Cli\WebhookShowFailedCommand;
use Altair\Webhooks\Storage\Delivery;
use Altair\Webhooks\Storage\DeliveryStatus;
use Altair\Webhooks\Storage\InMemoryDeliveryStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(WebhookShowFailedCommand::class)]
final class WebhookShowFailedCommandTest extends TestCase
{
    public function testReportsWhenNoDeadLetteredDeliveries(): void
    {
        [$exit, $output] = $this->invoke(new WebhookShowFailedCommand(new InMemoryDeliveryStore()));

        self::assertSame(0, $exit);
        self::assertStringContainsString('No dead-lettered deliveries.', $output);
    }

    public function testListsDeadLetteredDeliveries(): void
    {
        $store = new InMemoryDeliveryStore();
        $store->record($this->deadLettered('dlv_old', 1_000));
        $store->record($this->deadLettered('dlv_new', 2_000));

        [$exit, $output] = $this->invoke(new WebhookShowFailedCommand($store));

        self::assertSame(0, $exit);
        self::assertStringContainsString('dlv_old', $output);
        self::assertStringContainsString('dlv_new', $output);
        self::assertStringContainsString('HTTP 500', $output);
    }

    /**
     * @return array{int, string}
     */
    private function invoke(WebhookShowFailedCommand $command): array
    {
        ob_start();
        $exit = $command();

        return [$exit, (string) ob_get_clean()];
    }

    private function deadLettered(string $id, int $createdAt): Delivery
    {
        return Delivery::create(
            id: $id,
            eventName: 'order.created',
            subscriberUrl: 'https://example.test/hook',
            payload: '{}',
            secretName: 'partner-x',
            signerName: 'hmac-sha256',
            createdAt: $createdAt,
        )->withStatus(DeliveryStatus::DeadLettered)->withAttempts(5)->withLastResponse('HTTP 500');
    }
}
