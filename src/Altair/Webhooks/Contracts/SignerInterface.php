<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Webhooks\Contracts;

interface SignerInterface
{
    /**
     * The wire name of the scheme, e.g. 'hmac-sha256', 'hmac-sha512', 'ed25519'.
     * Carried by the spec block + OpenAPI extension; used by the registry,
     * middleware, and dispatcher to pick the signer the spec declared.
     */
    public function name(): string;

    /**
     * Produce a signature for the payload. The encoding (hex, base64, ...) is
     * the signer's choice; HMAC signers return hex to match Stripe / GitHub.
     */
    public function sign(string $payload, string $secret): string;

    /**
     * Constant-time verification of a signature against the payload. Returns
     * false rather than throwing on mismatch; never short-circuits on a
     * partial match (uses hash_equals for HMAC, sodium for Ed25519).
     */
    public function verify(string $payload, string $signature, string $secret): bool;
}
