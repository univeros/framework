<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Webhooks\Cli;

use Altair\Cli\Attribute\Command;
use Altair\Cli\Attribute\Option;
use Altair\Webhooks\Contracts\DeliveryStoreInterface;
use Altair\Webhooks\Storage\Delivery;

/**
 * `bin/altair webhook:show-failed` — list dead-lettered webhook deliveries,
 * oldest first, so an operator (or agent) can pick a `delivery-id` to feed
 * `webhook:replay`. Read-only; always exits `0`.
 */
#[Command(
    name: 'webhook:show-failed',
    description: 'List dead-lettered webhook deliveries.',
)]
final readonly class WebhookShowFailedCommand
{
    public function __construct(
        private DeliveryStoreInterface $deliveries,
    ) {}

    public function __invoke(
        #[Option(description: 'Maximum number of deliveries to list.')]
        int $limit = 100,
    ): int {
        $failed = $this->deliveries->findFailed(max(1, $limit));
        if ($failed === []) {
            echo "No dead-lettered deliveries.\n";

            return 0;
        }

        $rows = array_map(
            static fn(Delivery $delivery): array => [
                $delivery->id,
                $delivery->eventName,
                $delivery->subscriberUrl,
                (string) $delivery->attempts,
                $delivery->lastResponse ?? '',
            ],
            $failed,
        );

        echo $this->renderTable(['Delivery', 'Event', 'Subscriber', 'Attempts', 'Last response'], $rows);

        return 0;
    }

    /**
     * @param list<string>       $headers
     * @param list<list<string>> $rows
     */
    private function renderTable(array $headers, array $rows): string
    {
        $widths = array_map(strlen(...), $headers);
        foreach ($rows as $row) {
            foreach ($row as $i => $cell) {
                $widths[$i] = max($widths[$i], \strlen($cell));
            }
        }

        $line = static function (array $cells) use ($widths): string {
            $padded = [];
            foreach ($cells as $i => $cell) {
                $padded[] = str_pad((string) $cell, $widths[$i]);
            }

            return rtrim(implode('  ', $padded)) . "\n";
        };

        $out = $line($headers);
        $out .= $line(array_map(static fn(int $w): string => str_repeat('-', $w), $widths));
        foreach ($rows as $row) {
            $out .= $line($row);
        }

        return $out;
    }
}
