<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Tests\Http\Jwt;

use RuntimeException;

/**
 * Generates throwaway RSA key material for JWT tests so no secrets are committed.
 */
final class JwtTestKeys
{
    /**
     * @return array{0: string, 1: string} tuple of [privateKeyPem, publicKeyPem]
     */
    public static function rsaKeyPair(): array
    {
        $resource = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        if ($resource === false) {
            throw new RuntimeException('Unable to generate an RSA key pair for testing.');
        }

        openssl_pkey_export($resource, $privateKey);
        $details = openssl_pkey_get_details($resource);

        if ($details === false || !isset($details['key'])) {
            throw new RuntimeException('Unable to export the RSA public key for testing.');
        }

        return [$privateKey, $details['key']];
    }
}
