<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Scaffold\Spec\Ast;

/**
 * Webhook policy block on a spec.
 *
 * `direction: in` configures inbound verification (signature + timestamp
 * window + dedupe); `direction: out` configures an outbound dispatcher binding
 * (signing + retry + dead-letter).
 */
final readonly class WebhookSpec
{
    public const string DIRECTION_IN = 'in';

    public const string DIRECTION_OUT = 'out';

    public const string BACKOFF_EXPONENTIAL = 'exponential';

    public const string BACKOFF_LINEAR = 'linear';

    public function __construct(
        public string $direction,
        public string $signing,
        public ?string $secretName = null,
        public string $signatureHeader = 'X-Signature',
        public string $timestampHeader = 'X-Timestamp',
        public string $eventIdHeader = 'X-Event-Id',
        public string $dedupeTtl = '1h',
        public string $timestampWindow = '5m',
        public int $retryMaxAttempts = 5,
        public string $retryBackoff = self::BACKOFF_EXPONENTIAL,
        public string $retryBaseDelay = '30s',
        public ?string $deadLetterTransport = null,
    ) {}

    public function isInbound(): bool
    {
        return $this->direction === self::DIRECTION_IN;
    }

    public function isOutbound(): bool
    {
        return $this->direction === self::DIRECTION_OUT;
    }
}
