<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Cache;

use Altair\Cache\Exception\InvalidArgumentException;
use DateInterval;
use Override;
use Psr\Cache\CacheException as Psr6CacheException;
use Psr\Cache\CacheItemPoolInterface;
use Psr\SimpleCache\CacheInterface;
use Traversable;

class SimpleCache implements CacheInterface
{
    public function __construct(
        private readonly CacheItemPoolInterface $pool,
    ) {}

    #[Override]
    public function get(string $key, mixed $default = null): mixed
    {
        try {
            $item = $this->pool->getItem($key);
        } catch (Psr6CacheException $psr6CacheException) {
            throw new InvalidArgumentException($psr6CacheException->getMessage(), $psr6CacheException->getCode(), $psr6CacheException);
        }

        return $item->isHit() ? $item->get() : $default;
    }

    #[Override]
    public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
    {
        try {
            $item = $this->pool->getItem($key)->set($value);
        } catch (Psr6CacheException $psr6CacheException) {
            throw new InvalidArgumentException($psr6CacheException->getMessage(), $psr6CacheException->getCode(), $psr6CacheException);
        }

        if ($ttl !== null) {
            $item->expiresAfter($ttl);
        }

        return $this->pool->save($item);
    }

    #[Override]
    public function delete(string $key): bool
    {
        try {
            return $this->pool->deleteItem($key);
        } catch (Psr6CacheException $psr6CacheException) {
            throw new InvalidArgumentException($psr6CacheException->getMessage(), $psr6CacheException->getCode(), $psr6CacheException);
        }
    }

    #[Override]
    public function clear(): bool
    {
        return $this->pool->clear();
    }

    #[Override]
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $keys = $this->iterableToArray($keys);

        try {
            $items = $this->pool->getItems($keys);
        } catch (Psr6CacheException $psr6CacheException) {
            throw new InvalidArgumentException($psr6CacheException->getMessage(), $psr6CacheException->getCode(), $psr6CacheException);
        }

        $values = [];
        foreach ($items as $key => $item) {
            $values[$key] = $item->isHit() ? $item->get() : $default;
        }

        return $values;
    }

    /**
     * @param iterable<string, mixed> $values
     */
    #[Override]
    public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
    {
        $valuesIsArray = \is_array($values);
        if (!$valuesIsArray && !$values instanceof Traversable) {
            throw new InvalidArgumentException(\sprintf(
                'Cache values must be array or Traversable, "%s" given',
                get_debug_type($values),
            ));
        }

        $items = [];
        try {
            if ($valuesIsArray) {
                $keys = [];
                foreach ($values as $key => $value) {
                    $keys[] = $key;
                }

                $items = $this->pool->getItems($keys);
            } else {
                foreach ($values as $key => $value) {
                    $items[$key] = $this->pool->getItem($key)->set($value);
                }
            }
        } catch (Psr6CacheException $psr6CacheException) {
            throw new InvalidArgumentException($psr6CacheException->getMessage(), $psr6CacheException->getCode(), $psr6CacheException);
        }

        $success = true;
        foreach ($items as $key => $item) {
            if ($valuesIsArray) {
                $item->set($values[$key]);
            }

            if ($ttl !== null) {
                $item->expiresAfter($ttl);
            }

            $success = $this->pool->saveDeferred($item) && $success;
        }

        return $this->pool->commit() && $success;
    }

    #[Override]
    public function deleteMultiple(iterable $keys): bool
    {
        $keys = $this->iterableToArray($keys);

        try {
            return $this->pool->deleteItems($keys);
        } catch (Psr6CacheException $psr6CacheException) {
            throw new InvalidArgumentException($psr6CacheException->getMessage(), $psr6CacheException->getCode(), $psr6CacheException);
        }
    }

    #[Override]
    public function has(string $key): bool
    {
        try {
            return $this->pool->hasItem($key);
        } catch (Psr6CacheException $psr6CacheException) {
            throw new InvalidArgumentException($psr6CacheException->getMessage(), $psr6CacheException->getCode(), $psr6CacheException);
        }
    }

    /**
     * @param iterable<array-key, string> $values
     *
     * @return list<string>
     */
    private function iterableToArray(iterable $values): array
    {
        if ($values instanceof Traversable) {
            return iterator_to_array($values, false);
        }

        return array_values($values);
    }
}
