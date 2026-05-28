<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Http\Contracts;

use Lcobucci\JWT\Signer;

interface TokenConfigurationInterface
{
    /**
     * The public key used to sign the JWT token.
     *
     * @return non-empty-string
     */
    public function getPublicKey(): string;

    /**
     * The time-to-live for the token, in seconds.
     */
    public function getTtl(): int;

    public function getSigner(): Signer;

    /**
     * The stable issuer identifier (`iss` claim) for tokens minted and validated by this service.
     *
     * @return non-empty-string
     */
    public function getIssuer(): string;

    /**
     * The intended audience (`aud` claim), or null when no audience restriction applies.
     */
    public function getAudience(): ?string;

    /**
     * Returns the timestamp for token issuance and expiration in UNIX_TIMESTAMP format.
     */
    public function getTimestamp(): int;

    /**
     * Returns the expiration timestamp in UNIX_TIMESTAMP format.
     */
    public function getExpirationTimestamp(): int;

    /**
     * The private key used to sign the token.
     */
    public function getPrivateKey(): ?string;
}
