<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Security\Validator;

use Altair\Security\Contracts\EncrypterInterface;
use Exception;

class PayloadValidator
{
    /**
     * PayloadValidator constructor.
     */
    public function __construct(
        protected EncrypterInterface $encrypter,
        /**
         * @var array the payload to validate against
         */
        protected array $payload
    ) {}

    /**
     * Determines whether the payload has a valid format.
     * @throws Exception
     */
    public function validate(): bool
    {
        return isset($this->payload['iv'], $this->payload['value'], $this->payload['mac']) && $this->hasValidMac();
    }

    /**
     * Checks whether the passed payload has a valid mac value
     * @throws Exception
     */
    protected function hasValidMac(): bool
    {
        $bytes = random_bytes(EncrypterInterface::BLOCK_SIZE);
        $data = $this->encrypter->hash($this->payload['iv'], $this->payload['value']);
        $mac = hash_hmac(EncrypterInterface::HASH_SHA256_ALGORITHM, $data, $bytes, true);

        return hash_equals(
            hash_hmac(EncrypterInterface::HASH_SHA256_ALGORITHM, (string) $this->payload['mac'], $bytes, true),
            $mac
        );
    }
}
