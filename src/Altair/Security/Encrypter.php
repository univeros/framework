<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Security;

use Altair\Security\Contracts\EncrypterInterface;
use Altair\Security\Contracts\KeyInterface;
use Altair\Security\Exception\DecryptException;
use Altair\Security\Exception\EncryptException;
use Altair\Security\Exception\InvalidConfigException;
use Altair\Security\Validator\PayloadValidator;
use Exception;
use Override;

class Encrypter implements EncrypterInterface
{
    protected string $derivedKey;

    /**
     * Encrypter constructor.
     *
     * @param bool|list<class-string> $allowedClasses controls object reconstruction during
     *        decrypt(); see unserialize() docs. Default `false` rejects all classes (objects
     *        decode to __PHP_Incomplete_Class). Pass an explicit allow-list to permit named
     *        classes, or `true` to permit any class (not recommended).
     *
     * @throws InvalidConfigException
     */
    public function __construct(
        protected KeyInterface $key,
        protected string $cipher,
        protected bool|array $allowedClasses = false,
    ) {
        if (!\extension_loaded('openssl')) {
            throw new InvalidConfigException('Encryption requires the OpenSSL PHP extension.');
        }

        $this->derivedKey = $this->key->derive();

        if (!$this->supports($this->derivedKey, $this->cipher)) {
            throw new InvalidConfigException('Unsupported cipher and key length.');
        }
    }

    /**
     * Checks whether a given key and cipher combination is valid.
     *
     *
     */
    public function supports(string $key, string $cipher): bool
    {
        $length =  mb_strlen($key, '8bit');

        return ($cipher === EncrypterInterface::AES_128_CBC_CIPHER
                && $length === EncrypterInterface::AES_128_CBC_CIPHER_KEY_LENGTH) ||
            ($cipher === EncrypterInterface::AES_192_CBC_CIPHER
                && $length === EncrypterInterface::AES_192_CBC_CIPHER_KEY_LENGTH) ||
            ($cipher === EncrypterInterface::AES_256_CBC_CIPHER
                && $length === EncrypterInterface::AES_256_CBC_CIPHER_KEY_LENGTH);
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    #[Override]
    public function encrypt($value): string
    {
        $iv = random_bytes(EncrypterInterface::BLOCK_SIZE);
        $value = openssl_encrypt(serialize($value), $this->cipher, $this->derivedKey, 0, $iv);

        if (false === $value) {
            throw new EncryptException('Unable to encrypt the data.');
        }

        $iv = base64_encode($iv);

        $mac = $this->hash($iv, $value);

        $json = json_encode(['iv' => $iv, 'value' => $value, 'mac' => $mac]);

        if (!\is_string($json)) {
            throw new EncryptException('Unable to encrypt the data.');
        }

        return base64_encode($json);
    }

    /**
     * @inheritDoc
     * @throws DecryptException
     */
    #[Override]
    public function decrypt(string $payload): mixed
    {
        $data = $this->getPayload($payload);

        $value = openssl_decrypt($data['value'], $this->cipher, $this->derivedKey, 0, $data['iv']);

        if (false === $value) {
            throw new DecryptException('Unable to decrypt the data.');
        }

        return unserialize($value, ['allowed_classes' => $this->allowedClasses]);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function hash(string $iv, string $data, bool $raw = false): string
    {
        return hash_hmac(EncrypterInterface::HASH_SHA256_ALGORITHM, $iv . $data, $this->derivedKey, $raw);
    }

    /**
     *
     * @throws DecryptException
     * @throws Exception
     */
    protected function getPayload(string $payload): array
    {
        $payload = json_decode(base64_decode($payload), true);

        if ((new PayloadValidator($this, $payload))->validate()) {
            $payload['iv'] = base64_decode((string) $payload['iv']);

            return $payload;
        }

        throw new DecryptException('Payload structure or MAC is invalid.');
    }
}
