<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Webhooks\Dispatcher;

/**
 * Encodes the outbound retry backoff curve. Pure, immutable value object — the
 * handler reads delayFor() to compute the next attempt time it stamps on the
 * Delivery row.
 */
final readonly class RetryPolicy
{
    public const string EXPONENTIAL = 'exponential';

    public const string LINEAR = 'linear';

    public function __construct(
        public int $maxAttempts = 5,
        public string $backoff = self::EXPONENTIAL,
        public int $baseDelaySeconds = 30,
    ) {}

    /**
     * Delay in seconds before the given (1-based) attempt number.
     */
    public function delayFor(int $attempt): int
    {
        $attempt = max(1, $attempt);

        return match ($this->backoff) {
            self::LINEAR => $this->baseDelaySeconds * $attempt,
            self::EXPONENTIAL => $this->baseDelaySeconds * (2 ** ($attempt - 1)),
            default => $this->baseDelaySeconds,
        };
    }
}
