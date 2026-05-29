<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Observability\Trace;

use Random\RandomException;

/**
 * Identifies a span and ties it to a trace.
 *
 * Trace ids are 16 random bytes (32 lowercase hex chars) and span ids are 8
 * random bytes (16 lowercase hex chars) — the OpenTelemetry wire-format sizes,
 * so an exporter can emit them as the hex strings OTLP-JSON expects without
 * any further re-encoding. A child span shares its parent's `traceId` and
 * carries the parent's id in `parentSpanId`.
 */
final readonly class SpanContext
{
    public function __construct(
        public string $traceId,
        public string $spanId,
        public ?string $parentSpanId = null,
    ) {}

    public static function root(): self
    {
        return new self(self::randomHex(16), self::randomHex(8));
    }

    public function child(): self
    {
        return new self($this->traceId, self::randomHex(8), $this->spanId);
    }

    /**
     * @param int<1, max> $bytes
     */
    private static function randomHex(int $bytes): string
    {
        try {
            return bin2hex(random_bytes($bytes));
        } catch (RandomException) {
            // Fallback when the system CSPRNG is unavailable (extremely rare):
            // mt_rand is not cryptographically strong but ids are not secrets.
            $hex = '';
            while (\strlen($hex) < $bytes * 2) {
                $hex .= dechex(random_int(0, 0xFFFFFFFF));
            }

            return substr($hex, 0, $bytes * 2);
        }
    }
}
