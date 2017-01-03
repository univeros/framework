<?php
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
     * @inheritdoc
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
