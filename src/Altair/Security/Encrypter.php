<?php declare(strict_types=1);

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

class Encrypter implements EncrypterInterface
{
    /**
     * @var KeyInterface
     */
    protected $key;
    /**
     * @var string
     */
    protected $derivedKey;
    /**
     * @var string
     */
    protected $cipher;

    /**
     * Encrypter constructor.
     *
     * @param KeyInterface $key
     * @param string $cipher
     *
     * @throws InvalidConfigException
     */
    public function __construct(KeyInterface $key, string $cipher)
    {
        if (!extension_loaded('openssl')) {
            throw new InvalidConfigException('Encryption requires the OpenSSL PHP extension.');
        }

        $this->key = $key;
        $this->derivedKey = $key->derive();
        $this->cipher = $cipher;

        if (!$this->supports($this->derivedKey, $cipher)) {
            throw new InvalidConfigException('Unsupported cipher and key length.');
        }
    }

    /**
     * Checks whether a given key and cipher combination is valid.
     *
     * @param string $key
     * @param string $cipher
     *
     * @return bool
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

        if (!is_string($json)) {
            throw new EncryptException('Unable to encrypt the data.');
        }

        return base64_encode($json);
    }

    /**
     * @inheritDoc
     * @throws DecryptException
     */
    public function decrypt(string $payload)
    {
        $data = $this->getPayload($payload);

        $value = openssl_decrypt($data['value'], $this->cipher, $this->derivedKey, 0, $data['iv']);

        if (false === $value) {
            throw new DecryptException('Unable to decrypt the data.');
        }

        return unserialize($value, ['allowed_classes' => true]);
    }

    /**
     * @inheritDoc
     */
    public function hash(string $iv, string $data, bool $raw = false): string
    {
        return hash_hmac(EncrypterInterface::HASH_SHA256_ALGORITHM, $iv . $data, $this->derivedKey, $raw);
    }

    /**
     * @param string $payload
     *
     * @throws DecryptException
     * @throws Exception
     * @return array
     */
    protected function getPayload(string $payload): array
    {
        $payload = json_decode(base64_decode($payload), true);

        if ((new PayloadValidator($this, $payload))->validate()) {
            $payload['iv'] = base64_decode($payload['iv']);

            return $payload;
        }

        throw new DecryptException('Payload structure or MAC is invalid.');
    }
}
