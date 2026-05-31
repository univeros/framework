<?php

declare(strict_types=1);

namespace Altair\Tests\Webhooks\Cli;

use Altair\Webhooks\Cli\WebhookShowFailedCommand;
use Altair\Webhooks\Storage\Delivery;
use Altair\Webhooks\Storage\DeliveryStatus;
use Altair\Webhooks\Storage\InMemoryDeliveryStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(WebhookShowFailedCommand::class)]
final class WebhookShowFailedCommandTest extends TestCase
{
    public function testReportsWhenNoDeadLetteredDeliveries(): void
    {
        $tester = new CommandTester(new WebhookShowFailedCommand(new InMemoryDeliveryStore()));

        $exit = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('No dead-lettered deliveries.', $tester->getDisplay());
    }

    public function testListsDeadLetteredDeliveries(): void
    {
        $store = new InMemoryDeliveryStore();
        $store->record($this->deadLettered('dlv_old', 1_000));
        $store->record($this->deadLettered('dlv_new', 2_000));

        $tester = new CommandTester(new WebhookShowFailedCommand($store));

        $exit = $tester->execute([]);
        $display = $tester->getDisplay();

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('dlv_old', $display);
        self::assertStringContainsString('dlv_new', $display);
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
