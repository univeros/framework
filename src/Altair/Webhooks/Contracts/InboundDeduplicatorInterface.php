<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Webhooks\Contracts;

interface InboundDeduplicatorInterface
{
    /**
     * Atomically claim an inbound event id. Returns true on first-seen (the
     * caller won the race and should process the delivery); false when the id
     * has already been claimed within its TTL (a prior delivery owns it).
     */
    public function claim(string $eventId, int $ttlSeconds): bool;

    /**
     * Drop a claim. Used when the inbound handler crashed mid-processing so a
     * subsequent redelivery can be processed instead of being absorbed.
     */
    public function release(string $eventId): void;
}
