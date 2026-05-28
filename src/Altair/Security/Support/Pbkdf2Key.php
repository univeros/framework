<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Security\Support;

use Altair\Security\Exception\InvalidArgumentException;
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
        if ($length < 0) {
            throw new InvalidArgumentException(
                \sprintf('Derived key length must not be negative, "%d" given.', $length)
            );
        }

        if ($iterations < 1) {
            throw new InvalidArgumentException(
                \sprintf('Iterations must be a positive integer, "%d" given.', $iterations)
            );
        }

        parent::__construct($key, $salt, $length);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function derive(): string
    {
        $length = max(0, $this->length);
        $iterations = max(1, $this->iterations);

        return hash_pbkdf2($this->algorithm, $this->key, (string) $this->salt, $iterations, $length, true);
    }
}
