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
use Redis;

final readonly class RedisDeduplicator implements InboundDeduplicatorInterface
{
    public function __construct(
        private Redis $redis,
        private string $prefix = 'webhook:dedupe:',
    ) {}

    public function claim(string $eventId, int $ttlSeconds): bool
    {
        // SET key value NX EX ttl — atomic claim. Returns false when the key
        // already exists, so only the first caller for an eventId wins.
        $result = $this->redis->set(
            $this->prefix . $eventId,
            '1',
            ['nx', 'ex' => $ttlSeconds],
        );

        return $result !== false;
    }

    public function release(string $eventId): void
    {
        $this->redis->del($this->prefix . $eventId);
    }
}
