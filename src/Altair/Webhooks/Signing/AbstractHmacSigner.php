<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Webhooks\Signing;

use Altair\Webhooks\Contracts\SignerInterface;

/**
 * Shared HMAC implementation. Concrete signers only declare the hash algorithm.
 * Signatures are hex-encoded MACs (matches Stripe / GitHub conventions) and
 * verification is constant-time via hash_equals().
 */
abstract class AbstractHmacSigner implements SignerInterface
{
    public function name(): string
    {
        return 'hmac-' . $this->algo();
    }

    public function sign(string $payload, string $secret): string
    {
        return hash_hmac($this->algo(), $payload, $secret);
    }

    public function verify(string $payload, string $signature, string $secret): bool
    {
        $provided = $this->extractMac($signature);
        if ($provided === '') {
            return false;
        }

        // Constant-time comparison. hash_equals never short-circuits on a
        // partial match, which is the whole point for HMAC verification.
        return hash_equals($this->sign($payload, $secret), $provided);
    }
    abstract protected function algo(): string;

    /**
     * Accept either a bare hex MAC or Stripe's `t=<ts>,v1=<hex>` form (the v1=
     * component is extracted). Keeps the middleware signer-agnostic.
     */
    private function extractMac(string $signature): string
    {
        $trimmed = trim($signature);
        if ($trimmed === '') {
            return '';
        }

        if (!str_contains($trimmed, 'v1=')) {
            return $trimmed;
        }

        foreach (explode(',', $trimmed) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
            if ($key === 'v1') {
                return trim($value);
            }
        }

        return '';
    }
}
