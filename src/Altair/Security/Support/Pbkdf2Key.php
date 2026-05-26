<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Security\Support;

use Altair\Security\Exception\InvalidConfigException;
use Override;

class Pbkdf2Key extends AbstractKey
{
    /**
     * Pbkdf2Key constructor.
     */
    public function __construct(
        string $key,
        string $salt,
        int $length = 0,
        protected int $iterations = 100000
    ) {
        parent::__construct($key, $salt, $length);
    }

    /**
     * @inheritDoc
     *
     * @throws InvalidConfigException
     */
    #[Override]
    public function derive(): string
    {
        $outputKey = hash_pbkdf2($this->algorithm, $this->key, $this->salt, $this->iterations, $this->length, true);

        if ($outputKey === false) {
            throw new InvalidConfigException('Invalid parameters to hash_pbkdf2().');
        }

        return $outputKey;
    }
}
