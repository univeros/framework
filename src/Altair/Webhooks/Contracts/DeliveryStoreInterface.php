<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Webhooks\Contracts;

use Altair\Webhooks\Storage\Delivery;

interface DeliveryStoreInterface
{
    /**
     * Persist a brand-new delivery. Implementations may assume the id is unique.
     */
    public function record(Delivery $delivery): void;

    /**
     * Overwrite an existing delivery with an updated copy (same id).
     */
    public function update(Delivery $delivery): void;

    public function findById(string $deliveryId): ?Delivery;

    /**
     * Dead-lettered deliveries, oldest first.
     *
     * @return list<Delivery>
     */
    public function findFailed(int $limit = 100): array;
}
