<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Security\Validator;

use Altair\Security\Contracts\EncrypterInterface;

class PayloadValidator
{
    /**
     * @var EncrypterInterface
     */
    protected $encrypter;
    /**
     * @var array the payload to validate against
     */
    protected $payload;

    /**
     * PayloadValidator constructor.
     *
     * @param EncrypterInterface $encrypter
     * @param array $payload
     */
    public function __construct(EncrypterInterface $encrypter, array $payload)
    {
        $this->encrypter = $encrypter;
        $this->payload = $payload;
    }

    /**
     * Determines whether the payload has a valid format.
     *
     * @return bool
     */
    public function validate(): bool
    {
        return isset($this->payload['iv'], $this->payload['value'], $this->payload['mac']) && $this->hasValidMac();
    }

    /**
     * Checks whether the passed payload has a valid mac value
     *
     * @return bool
     */
    protected function hasValidMac(): bool
    {
        $bytes = random_bytes(EncrypterInterface::BLOCK_SIZE);
        $data = $this->encrypter->hash($this->payload['iv'], $this->payload['value']);
        $mac = hash_hmac(EncrypterInterface::HASH_SHA256_ALGORITHM, $data, $bytes, true);

        return hash_equals(
            hash_hmac(EncrypterInterface::HASH_SHA256_ALGORITHM, $this->payload['mac'], $bytes, true),
            $mac
        );
    }
}
