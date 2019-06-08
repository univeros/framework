<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Security\Support;

use Altair\Security\Exception\InvalidConfigException;

class Pbkdf2Key extends AbstractKey
{
    /**
     * @var int
     */
    protected $iterations;

    /**
     * Pbkdf2Key constructor.
     *
     * @param string $key
     * @param string $salt
     * @param int $length
     * @param int $iterations
     */
    public function __construct(
        string $key,
        string $salt,
        int $length = 0,
        int $iterations = 100000
    ) {
        parent::__construct($key, $salt, $length);

        $this->iterations = $iterations;
    }

    /**
     * @inheritDoc
     */
    public function derive(): string
    {
        $outputKey = hash_pbkdf2($this->algorithm, $this->key, $this->salt, $this->iterations, $this->length, true);

        if ($outputKey === false) {
            throw new InvalidConfigException('Invalid parameters to hash_pbkdf2().');
        }

        return $outputKey;
    }
}
