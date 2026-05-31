<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Webhooks\Support;

use Altair\Webhooks\Exception\WebhookException;

/**
 * Translates a duration string carried in a spec / OpenAPI extension
 * (`<number><ms|s|m|h|d>`) into whole seconds. Pure, deterministic, no clock.
 * Mirrors the pattern the idempotency spec validator enforces.
 */
final readonly class DurationParser
{
    private const array MULTIPLIERS = [
        's' => 1,
        'm' => 60,
        'h' => 3_600,
        'd' => 86_400,
    ];

    public function toSeconds(string $duration): int
    {
        if (preg_match('/^(\d+)(ms|s|m|h|d)$/', $duration, $match) !== 1) {
            throw new WebhookException(\sprintf(
                "Duration '%s' must match '<number><ms|s|m|h|d>' (e.g. '1h', '5m', '500ms').",
                $duration,
            ));
        }

        $value = (int) $match[1];
        $unit = $match[2];

        if ($unit === 'ms') {
            // Sub-second durations round up to at least 1 second on the wire.
            return $value > 0 ? max(1, (int) ceil($value / 1000)) : 0;
        }

        return $value * self::MULTIPLIERS[$unit];
    }
}
