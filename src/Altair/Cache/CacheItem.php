<?php

namespace Altair\Cache;

use Altair\Cache\Contracts\CacheItemTagValidatorInterface;
use Altair\Cache\Exception\InvalidArgumentException;
use DateInterval;
use DateTime;
use DateTimeInterface;
use Psr\Cache\CacheItemInterface;

final class CacheItem implements CacheItemInterface
{
    protected $key;
    protected $value;
    protected $isHit;
    protected $expirationTime;
    protected $defaultLifespan;
    protected $tags = [];
    /**
     * @var CacheItemTagValidatorInterface
     */
    protected $tagValidator;

    /**
     * @inheritdoc
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * @inheritdoc
     */
    public function get()
    {
        return $this->value;
    }

    /**
     * @inheritdoc
     */
    public function isHit()
    {
        return $this->isHit;
    }

    /**
     * @inheritdoc
     */
    public function set($value)
    {
        $this->value = $value;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function expiresAt($expiration)
    {
        if (null === $expiration) {
            $this->expirationTime = $this->defaultLifespan > 0 ? time() + $this->defaultLifespan : null;
        } elseif ($expiration instanceof DateTimeInterface) {
            $this->expirationTime = $expiration->getTimestamp();
        } else {
            throw new InvalidArgumentException(
                sprintf(
                    'The expiration time must be null or implement DateTimeInterface, %s given',
                    gettype($expiration)
                )
            );
        }

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function expiresAfter($time)
    {
        if (null === $time) {
            $this->expirationTime = $this->defaultLifespan > 0 ? time() + $this->defaultLifespan : null;
        } elseif ($time instanceof DateInterval) {
            $this->expirationTime = DateTime::createFromFormat('U', time())->add($time)->format('U');
        } elseif (is_int($time)) {
            $this->expirationTime = time() + $time;
        } else {
            throw new InvalidArgumentException(
                sprintf('The expiration time must be an integer, a DateInterval or null, %s given', gettype($time))
            );
        }

        return $this;
    }
}
