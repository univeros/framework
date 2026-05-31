<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Webhooks\Storage;

use Altair\Webhooks\Contracts\DeliveryStoreInterface;

final class InMemoryDeliveryStore implements DeliveryStoreInterface
{
    /** @var array<string, Delivery> */
    private array $deliveries = [];

    public function record(Delivery $delivery): void
    {
        $this->deliveries[$delivery->id] = $delivery;
    }

    public function update(Delivery $delivery): void
    {
        $this->deliveries[$delivery->id] = $delivery;
    }

    public function findById(string $deliveryId): ?Delivery
    {
        return $this->deliveries[$deliveryId] ?? null;
    }

    public function findFailed(int $limit = 100): array
    {
        $failed = array_values(array_filter(
            $this->deliveries,
            static fn(Delivery $delivery): bool => $delivery->status === DeliveryStatus::DeadLettered,
        ));

        usort(
            $failed,
            static fn(Delivery $a, Delivery $b): int => [$a->createdAt, $a->id] <=> [$b->createdAt, $b->id],
        );

        return \array_slice($failed, 0, $limit);
    }
}
