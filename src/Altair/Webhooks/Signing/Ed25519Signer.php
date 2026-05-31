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
use Altair\Webhooks\Exception\WebhookException;
use SodiumException;

/**
 * Ed25519 detached-signature signer. Requires ext-sodium and fails fast at
 * construction when it is absent (same pattern as Idempotency's ApcuStore).
 *
 * Keys and signatures are hex-encoded. For signing, the resolved secret is the
 * 64-byte Ed25519 secret key; for verifying, it is the 32-byte public key.
 */
final class Ed25519Signer implements SignerInterface
{
    public function __construct()
    {
        if (!\extension_loaded('sodium')) {
            throw WebhookException::signerUnavailable('ed25519', 'ext-sodium is not installed.');
        }
    }

    public function name(): string
    {
        return 'ed25519';
    }

    public function sign(string $payload, string $secret): string
    {
        $key = $this->decodeKey($secret, SODIUM_CRYPTO_SIGN_SECRETKEYBYTES, 'secret');

        return sodium_bin2hex(sodium_crypto_sign_detached($payload, $key));
    }

    public function verify(string $payload, string $signature, string $secret): bool
    {
        try {
            $sig = sodium_hex2bin(trim($signature));
            $publicKey = sodium_hex2bin(trim($secret));
        } catch (SodiumException) {
            return false;
        }

        if (\strlen($sig) !== SODIUM_CRYPTO_SIGN_BYTES) {
            return false;
        }

        if (\strlen($publicKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            return false;
        }

        try {
            return sodium_crypto_sign_verify_detached($sig, $payload, $publicKey);
        } catch (SodiumException) {
            return false;
        }
    }

    /**
     * @return non-empty-string
     */
    private function decodeKey(string $hex, int $expectedBytes, string $label): string
    {
        try {
            $key = sodium_hex2bin(trim($hex));
        } catch (SodiumException) {
            throw WebhookException::signerUnavailable('ed25519', \sprintf('the %s key is not valid hex.', $label));
        }

        if ($key === '' || \strlen($key) !== $expectedBytes) {
            throw WebhookException::signerUnavailable(
                'ed25519',
                \sprintf('the %s key must be %d bytes (%d hex chars).', $label, $expectedBytes, $expectedBytes * 2),
            );
        }

        return $key;
    }
}
