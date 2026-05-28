<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Cache;

use Altair\Cache\Contracts\CacheItemTagValidatorInterface;
use DateInterval;
use DateTime;
use DateTimeInterface;
use Override;
use Psr\Cache\CacheItemInterface;

final class CacheItem implements CacheItemInterface
{
    // The following properties are accessed via Closure::bind reflection from
    // CacheItemPool (see createCacheItemFactoryClosure / createDeferredMergerClosure).
    // Static analysers cannot see those references — do not "clean up" as unused.
    protected string $key;

    protected mixed $value;

    protected bool $isHit;

    protected ?int $expirationTime = null;

    protected ?int $defaultLifespan = null;

    /**
     * @var array<int, string>
     */
    protected array $tags = [];

    protected ?CacheItemTagValidatorInterface $tagValidator = null;

    #[Override]
    public function getKey(): string
    {
        return $this->key;
    }

    #[Override]
    public function get(): mixed
    {
        return $this->value;
    }

    #[Override]
    public function isHit(): bool
    {
        return $this->isHit;
    }

    #[Override]
    public function set(mixed $value): static
    {
        $this->value = $value;

        return $this;
    }

    #[Override]
    public function expiresAt(?DateTimeInterface $expiration): static
    {
        $this->expirationTime = $expiration instanceof DateTimeInterface
            ? ($expiration->getTimestamp())
            : ($this->defaultLifespan > 0 ? time() + $this->defaultLifespan : null);

        return $this;
    }

    #[Override]
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
