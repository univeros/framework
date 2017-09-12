<?php

namespace Altair\Http\Contracts;

use Lcobucci\JWT\Signer;

interface TokenConfigurationInterface
{
    /**
     * The public key used to sign the JWT token.
     *
     * @return string
     */
    public function getPublicKey(): string;

    /**
     * The time-to-live for the token, in seconds.
     *
     * @return integer
     */
    public function getTtl(): int;

    /**
     * @return Signer
     */
    public function getSigner(): Signer;

    /**
     * Returns the timestamp for token issuance and expiration in UNIX_TIMESTAMP format.
     * @return integer
     */
    public function getTimestamp(): int;

    /**
     * Returns the expiration timestamp in UNIX_TIMESTAMP format.
     * @return int
     */
    public function getExpirationTimestamp(): int;

    /**
     * The private key used to sign the token.
     * @return string|null
     */
    public function getPrivateKey(): ?string;
}
