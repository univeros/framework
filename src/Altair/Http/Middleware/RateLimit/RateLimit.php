<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Http\Middleware\RateLimit;

use InvalidArgumentException;

/**
 * The policy attached to one {@see RateLimitMiddleware}: how many requests
 * per fixed window, and a cache-key prefix so multiple limiters on the same
 * cache backend don't collide.
 *
 * Construction validates that limit and window are positive — a zero or
 * negative configuration would degenerate into "always 429" or "always pass"
 * and is almost certainly a bug.
 */
final readonly class RateLimit
{
    public function __construct(
        public int $limit,
        public int $windowSeconds,
        public string $keyPrefix = 'ratelimit',
    ) {
        if ($limit < 1) {
            throw new InvalidArgumentException(\sprintf('Rate-limit `limit` must be >= 1, got %d.', $limit));
        }

        if ($windowSeconds < 1) {
            throw new InvalidArgumentException(\sprintf('Rate-limit `windowSeconds` must be >= 1, got %d.', $windowSeconds));
        }
    }
}
