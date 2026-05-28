<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Observatory\Panel;

use Altair\Observatory\Contracts\FailedQueueReaderInterface;
use Altair\Observatory\Contracts\PanelInterface;
use Override;

/**
 * Surfaces the messaging layer as a panel: how many transports are wired and
 * how many jobs have landed in the failure transport, with the most recent
 * failures listed.
 *
 * It reads through {@see FailedQueueReaderInterface} only — a narrow,
 * framework-owned seam the host adapts to its real failure transport — so the
 * panel never touches Symfony Messenger or a live broker and stays trivially
 * testable against an in-memory fake.
 */
final readonly class QueuesPanel implements PanelInterface
{
    /**
     * @param int $tail maximum number of recent failures to list
     */
    public function __construct(
        private FailedQueueReaderInterface $reader,
        private int $tail = 25,
    ) {}

    #[Override]
    public function id(): string
    {
        return 'queues';
    }

    #[Override]
    public function label(): string
    {
        return 'Queues';
    }

    #[Override]
    public function icon(): string
    {
        return 'queue-list';
    }

    #[Override]
    public function snapshot(): PanelSnapshot
    {
        $transports = $this->reader->transportNames();
        $failed = $this->reader->failedCount();

        $status = match (true) {
            $transports === [] => PanelStatus::Unknown,
            $failed > 0 => PanelStatus::Warning,
            default => PanelStatus::Ok,
        };

        return new PanelSnapshot(
            $status,
            $this->headline($transports, $failed),
            [
                'failed' => $failed,
                'transports' => \count($transports),
            ],
            $failed > 0 ? $this->reader->recentFailures($this->tail) : [],
        );
    }

    /**
     * @param list<string> $transports
     */
    private function headline(array $transports, int $failed): string
    {
        if ($transports === []) {
            return 'No transports configured';
        }

        return \sprintf('%s failed', number_format($failed));
    }
}
