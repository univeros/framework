<?php
namespace Altair\Security\Support;

use Altair\Security\Contracts\EncrypterInterface;
use Altair\Security\Contracts\KeyInterface;
use Altair\Security\Exception\InvalidConfigException;

abstract class AbstractKey implements KeyInterface
{
    /**
     * @var string
     */
    protected $algorithm;
    /**
     * @var string
     */
    protected $key;
    /**
     * @var string
     */
    protected $salt;
    /**
     * @var int
     */
    protected $length;

    /**
     * AbstractKey constructor.
     *
     * @param string $key
     * @param string|null $salt
     * @param int $length
     *
     * @throws InvalidConfigException
     */
    public function __construct(string $key, string $salt = null, int $length = 0)
    {
        $this->algorithm = EncrypterInterface::HASH_SHA256_ALGORITHM;
        $this->key = $key;
        $this->salt = $salt;
        $this->length = $length;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->derive();
    }
}
