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
use Redis;

/**
 * Redis-backed delivery store. Each delivery is serialized under
 * "<prefix><id>"; dead-lettered ids are also tracked in a sorted set scored by
 * createdAt so findFailed() can return them oldest-first without scanning.
 */
final readonly class RedisDeliveryStore implements DeliveryStoreInterface
{
    public function __construct(
        private Redis $redis,
        private string $prefix = 'webhook:delivery:',
        private string $deadLetterIndex = 'webhook:deliveries:deadletter',
    ) {}

    public function record(Delivery $delivery): void
    {
        $this->persist($delivery);
    }

    public function update(Delivery $delivery): void
    {
        $this->persist($delivery);
    }

    public function findById(string $deliveryId): ?Delivery
    {
        $raw = $this->redis->get($this->prefix . $deliveryId);
        if (!\is_string($raw)) {
            return null;
        }

        $value = unserialize($raw, ['allowed_classes' => [Delivery::class, DeliveryStatus::class]]);

        return $value instanceof Delivery ? $value : null;
    }

    public function findFailed(int $limit = 100): array
    {
        if ($limit < 1) {
            return [];
        }

        /** @var list<string> $ids */
        $ids = $this->redis->zRange($this->deadLetterIndex, 0, $limit - 1);

        $deliveries = [];
        foreach ($ids as $id) {
            $delivery = $this->findById($id);
            if ($delivery instanceof Delivery) {
                $deliveries[] = $delivery;
            }
        }

        return $deliveries;
    }

    private function persist(Delivery $delivery): void
    {
        $this->redis->set($this->prefix . $delivery->id, serialize($delivery));

        if ($delivery->status === DeliveryStatus::DeadLettered) {
            $this->redis->zAdd($this->deadLetterIndex, $delivery->createdAt, $delivery->id);
        } else {
            $this->redis->zRem($this->deadLetterIndex, $delivery->id);
        }
    }
}
