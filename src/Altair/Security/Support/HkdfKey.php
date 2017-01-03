<?php
namespace Altair\Security\Support;

use Altair\Security\Exception\InvalidConfigException;

class HkdfKey extends AbstractKey
{
    /**
     * @var string
     */
    protected $context;
    /**
     * @var int
     */
    protected $hashLength;

    /**
     * HkdfKey constructor.
     *
     * @param string $key
     * @param string|null $salt
     * @param string|null $context
     * @param int $length
     *
     * @throws InvalidConfigException
     */
    public function __construct(
        string $key,
        string $salt = null,
        string $context = null,
        int $length = 0
    ) {

        parent::__construct($key, $salt, $length);

        $hash = hash_hmac($this->algorithm, '', '', true);

        $this->hashLength = mb_strlen($hash, '8bit');

        if ($length < 0 || $length > 255 * $this->hashLength) {
            throw new InvalidConfigException('Invalid length: ' . $length);
        }

        $this->context = $context;
    }

    /**
     * @return string
     */
    public function derive(): string
    {
        $blocks = $this->length !== 0 ? ceil($this->length / $this->hashLength) : 1;

        if ($this->salt === null) {
            $this->salt = str_repeat("\0", $this->hashLength);
        }

        $prKey = hash_hmac($this->algorithm, $this->key, $this->salt, true);

        $hmac = '';
        $outputKey = '';
        for ($i = 1; $i <= $blocks; $i++) {
            $hmac = hash_hmac($this->algorithm, $hmac . $this->context . chr($i), $prKey, true);
            $outputKey .= $hmac;
        }

        if ($this->length !== 0) {
            $outputKey = mb_substr($outputKey, 0, $this->length, '8bit');
        }
        return $outputKey;
    }
}
