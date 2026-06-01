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

    public const string DEFAULT_SIGNATURE_HEADER = 'X-Signature';

    public const string DEFAULT_TIMESTAMP_HEADER = 'X-Timestamp';

    public const string DEFAULT_EVENT_ID_HEADER = 'X-Event-Id';

    public const string DEFAULT_DEDUPE_TTL = '1h';

    public const string DEFAULT_TIMESTAMP_WINDOW = '5m';

    public const int DEFAULT_RETRY_MAX_ATTEMPTS = 5;

    public const string DEFAULT_RETRY_BASE_DELAY = '30s';

    public function __construct(
        public string $direction,
        public string $signing,
        public ?string $secretName = null,
        public string $signatureHeader = self::DEFAULT_SIGNATURE_HEADER,
        public string $timestampHeader = self::DEFAULT_TIMESTAMP_HEADER,
        public string $eventIdHeader = self::DEFAULT_EVENT_ID_HEADER,
        public string $dedupeTtl = self::DEFAULT_DEDUPE_TTL,
        public string $timestampWindow = self::DEFAULT_TIMESTAMP_WINDOW,
        public int $retryMaxAttempts = self::DEFAULT_RETRY_MAX_ATTEMPTS,
        public string $retryBackoff = self::BACKOFF_EXPONENTIAL,
        public string $retryBaseDelay = self::DEFAULT_RETRY_BASE_DELAY,
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
