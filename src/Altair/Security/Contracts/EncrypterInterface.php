<?php
namespace Altair\Security\Contracts;

interface EncrypterInterface
{
    const AES_128_CBC_CIPHER = 'AES-128-CBC';
    const AES_192_CBC_CIPHER = 'AES-192-CBC';
    const AES_256_CBC_CIPHER = 'AES-256-CBC';

    const AES_128_CBC_CIPHER_KEY_LENGTH = 16;
    const AES_192_CBC_CIPHER_KEY_LENGTH = 24;
    const AES_256_CBC_CIPHER_KEY_LENGTH = 32;

    const BLOCK_SIZE = 16;

    const HASH_SHA256_ALGORITHM = 'sha256';

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
