<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Idempotency\Hash;

use Altair\Idempotency\Exception\IdempotencyException;

/**
 * Translates a TTL string carried in a spec / OpenAPI extension into
 * seconds for the storage adapter. Same pattern the spec validator
 * enforces: `<number><ms|s|m|h|d>`.
 *
 * Pure utility. No dependencies, no clock. Deterministic.
 */
final readonly class TtlParser
{
    private const array MULTIPLIERS = [
        'ms' => 0, // milliseconds round down to 0 seconds at the storage layer
        's' => 1,
        'm' => 60,
        'h' => 3_600,
        'd' => 86_400,
    ];

    public function toSeconds(string $ttl): int
    {
        if (preg_match('/^(\d+)(ms|s|m|h|d)$/', $ttl, $match) !== 1) {
            throw new IdempotencyException(\sprintf(
                "TTL '%s' must match '<number><ms|s|m|h|d>' (e.g. '24h', '500ms', '7d').",
                $ttl,
            ));
        }

        $value = (int) $match[1];
        $unit = $match[2];

        if ($unit === 'ms') {
            // Storage TTLs are in seconds; sub-second TTLs are meaningful for
            // the spec but always round up to at least 1 second on the wire.
            return $value > 0 ? max(1, (int) ceil($value / 1000)) : 0;
        }

        return $value * self::MULTIPLIERS[$unit];
    }
}
