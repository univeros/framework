<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Webhooks\Storage;

use Altair\Webhooks\Contracts\InboundDeduplicatorInterface;

final class InMemoryDeduplicator implements InboundDeduplicatorInterface
{
    /** @var array<string, int> eventId => expiresAt (epoch seconds) */
    private array $claims = [];

    public function claim(string $eventId, int $ttlSeconds): bool
    {
        $this->purgeExpired();

        if (isset($this->claims[$eventId])) {
            return false;
        }

        $this->claims[$eventId] = $this->now() + $ttlSeconds;

        return true;
    }

    public function release(string $eventId): void
    {
        unset($this->claims[$eventId]);
    }

    private function purgeExpired(): void
    {
        $now = $this->now();
        foreach ($this->claims as $eventId => $expiresAt) {
            if ($expiresAt <= $now) {
                unset($this->claims[$eventId]);
            }
        }
    }

    private function now(): int
    {
        return time();
    }
}
