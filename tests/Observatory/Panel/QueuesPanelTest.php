<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Tests\Observatory\Panel;

use Altair\Observatory\Contracts\FailedQueueReaderInterface;
use Altair\Observatory\Panel\PanelStatus;
use Altair\Observatory\Panel\QueuesPanel;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(QueuesPanel::class)]
final class QueuesPanelTest extends TestCase
{
    public function testIdentity(): void
    {
        $panel = new QueuesPanel($this->reader([], 0));

        self::assertSame('queues', $panel->id());
        self::assertSame('Queues', $panel->label());
        self::assertSame('queue-list', $panel->icon());
    }

    public function testStatusIsUnknownWhenNoTransportsConfigured(): void
    {
        $snapshot = (new QueuesPanel($this->reader([], 0)))->snapshot();

        self::assertSame(PanelStatus::Unknown, $snapshot->status);
        self::assertSame('No transports configured', $snapshot->headline);
        self::assertSame(0, $snapshot->metrics['transports']);
        self::assertSame(0, $snapshot->metrics['failed']);
        self::assertSame([], $snapshot->items);
    }

    public function testStatusIsOkWhenTransportsConfiguredAndNoFailures(): void
    {
        $snapshot = (new QueuesPanel($this->reader(['default', 'high'], 0)))->snapshot();

        self::assertSame(PanelStatus::Ok, $snapshot->status);
        self::assertSame('0 failed', $snapshot->headline);
        self::assertSame(2, $snapshot->metrics['transports']);
        self::assertSame(0, $snapshot->metrics['failed']);
        self::assertSame([], $snapshot->items, 'No failures should be listed when there are none.');
    }

    public function testStatusIsWarningWhenFailuresPresent(): void
    {
        $failures = [
            ['id' => 'env-1', 'message_class' => 'App\\Messages\\SendWelcomeEmail', 'error' => 'SMTP timeout', 'transport' => 'failed'],
            ['id' => 'env-2', 'message_class' => 'App\\Messages\\GenerateReport', 'error' => 'OOM', 'transport' => 'failed'],
            ['id' => 'env-3', 'message_class' => 'App\\Messages\\Resize', 'error' => 'bad image', 'transport' => 'failed'],
        ];

        $snapshot = (new QueuesPanel($this->reader(['default'], 3, $failures)))->snapshot();

        self::assertSame(PanelStatus::Warning, $snapshot->status);
        self::assertSame('3 failed', $snapshot->headline);
        self::assertSame(1, $snapshot->metrics['transports']);
        self::assertSame(3, $snapshot->metrics['failed']);
        self::assertCount(3, $snapshot->items);
        self::assertSame('env-1', $snapshot->items[0]['id']);
        self::assertSame('App\\Messages\\SendWelcomeEmail', $snapshot->items[0]['message_class']);
    }

    public function testHeadlineFormatsLargeCountsWithThousandsSeparator(): void
    {
        $snapshot = (new QueuesPanel($this->reader(['default'], 1234)))->snapshot();

        self::assertSame('1,234 failed', $snapshot->headline);
        self::assertSame(1234, $snapshot->metrics['failed']);
    }

    public function testTailLimitIsForwardedToReader(): void
    {
        $reader = new class implements FailedQueueReaderInterface {
            public ?int $requestedLimit = null;

            #[Override]
            public function transportNames(): array
            {
                return ['default'];
            }

            #[Override]
            public function failedCount(): int
            {
                return 5;
            }

            #[Override]
            public function recentFailures(int $limit = 25): array
            {
                $this->requestedLimit = $limit;

                return [];
            }
        };

        (new QueuesPanel($reader, 10))->snapshot();

        self::assertSame(10, $reader->requestedLimit);
    }

    /**
     * @param list<string>                     $transports
     * @param list<array<string, scalar|null>> $failures
     */
    private function reader(array $transports, int $failedCount, array $failures = []): FailedQueueReaderInterface
    {
        return new readonly class ($transports, $failedCount, $failures) implements FailedQueueReaderInterface {
            /**
             * @param list<string>                     $transports
             * @param list<array<string, scalar|null>> $failures
             */
            public function __construct(
                private array $transports,
                private int $failedCount,
                private array $failures,
            ) {}

            #[Override]
            public function transportNames(): array
            {
                return $this->transports;
            }

            #[Override]
            public function failedCount(): int
            {
                return $this->failedCount;
            }

            #[Override]
            public function recentFailures(int $limit = 25): array
            {
                return \array_slice($this->failures, 0, $limit);
            }
        };
    }
}
