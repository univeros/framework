<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Security\Contracts;

interface EncrypterInterface
{
    public const AES_128_CBC_CIPHER = 'AES-128-CBC';
    public const AES_192_CBC_CIPHER = 'AES-192-CBC';
    public const AES_256_CBC_CIPHER = 'AES-256-CBC';

    public const AES_128_CBC_CIPHER_KEY_LENGTH = 16;
    public const AES_192_CBC_CIPHER_KEY_LENGTH = 24;
    public const AES_256_CBC_CIPHER_KEY_LENGTH = 32;

    public const BLOCK_SIZE = 16;

    public const HASH_SHA256_ALGORITHM = 'sha256';

    /**
     * Encrypts a given value. Returns a base64encode'd JSON string.
     *
     * @param mixed $value
     *
     * @return string
     */
    public function encrypt($value): string;

    /**
     * Decrypts a value. The value represents a base64encode'd JSON string with the values required for decryption.
     *
     * @param string $payload
     *
     * @return mixed
     */
    public function decrypt(string $payload);

    /**
     * Creates a MAC for a given value.
     *
     * @param string $iv initialization vector
     * @param string $data the data to be protected
     * @param bool $raw whether it should be raw
     *
     * @return string
     */
    public function hash(string $iv, string $data, bool $raw = false): string;
}
