<?php
namespace Altair\Cache;

use Altair\Cache\Contracts\CacheItemTagValidatorInterface;
use Altair\Cache\Contracts\TagAwareCacheItemInterface;
use Altair\Cache\Exception\InvalidArgumentException;
use DateInterval;
use DateTime;
use DateTimeInterface;

final class CacheItem implements TagAwareCacheItemInterface
{
    protected $key;
    protected $value;
    protected $hasValue;
    protected $expirationTime;
    protected $defaultExpirationTime;
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
        if (!$this->hasValue) {
            return false;
        }

        $now = time();

        if (null !== $this->expirationTime) {
            return $this->expirationTime > $now;
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function set($value)
    {
        $this->value = $value;
        $this->hasValue = true;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function expiresAt($expiration)
    {
        if (null === $expiration) {
            $this->expirationTime = $this->defaultExpirationTime > 0 ? time() + $this->defaultExpirationTime : null;
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
            $this->expirationTime = $this->defaultExpirationTime > 0 ? time() + $this->defaultExpirationTime : null;
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

    /**
     * @inheritdoc
     */
    public function withTag(string $tag): TagAwareCacheItemInterface
    {
        return $this->withTags([$tag]);
    }

    /**
     * @inheritdoc
     */
    public function withTags(array $tags): TagAwareCacheItemInterface
    {
        $cloned = clone $this;
        $reason = '';
        foreach ($tags as $tag) {
            if (is_string($tag) && isset($cloned->tags[$tag])) {
                continue;
            }

            if (!$cloned->tagValidator->validate($tag, $reason)) {
                throw new InvalidArgumentException($reason);
            }
            $cloned->tags[$tag];
        }

        return $cloned;
    }
}
