<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

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
     * @inheritDoc
     */
    public function getKey(): string
    {
        return $this->key;
    }

    public function get(): mixed
    {
        return $this->value;
    }

    public function isHit(): bool
    {
        return $this->isHit;
    }

    public function set(mixed $value): static
    {
        $this->value = $value;

        return $this;
    }

    public function expiresAt(?DateTimeInterface $expiration): static
    {
        $this->expirationTime = $expiration === null
            ? ($this->defaultLifespan > 0 ? time() + $this->defaultLifespan : null)
            : $expiration->getTimestamp();

        return $this;
    }

    public function expiresAfter(int|DateInterval|null $time): static
    {
        if ($time === null) {
            $this->expirationTime = $this->defaultLifespan > 0 ? time() + $this->defaultLifespan : null;

            return $this;
        }

        if ($time instanceof DateInterval) {
            $this->expirationTime = (int) DateTime::createFromFormat('Y-m-d H:i:s', date('Y-m-d H:i:s'))
                ->add($time)
                ->format('U');

            return $this;
        }

        $this->expirationTime = time() + $time;

        return $this;
    }
}
