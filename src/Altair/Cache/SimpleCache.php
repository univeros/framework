<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Cache;

use Altair\Cache\Exception\InvalidArgumentException;
use Psr\Cache\CacheException as Psr6CacheException;
use Psr\Cache\CacheItemPoolInterface;
use Psr\SimpleCache\CacheException as SimpleCacheException;
use Psr\SimpleCache\CacheInterface;
use Traversable;

class SimpleCache implements CacheInterface
{
    protected $pool;

    /**
     * SimpleCache constructor.
     *
     * @param CacheItemPoolInterface $pool
     */
    public function __construct(CacheItemPoolInterface $pool)
    {
        $this->pool = $pool;
    }

    /**
     * @inheritDoc
     *
     * @throws SimpleCacheException
     */
    public function get($key, $default = null)
    {
        try {
            $item = $this->pool->getItem($key);
        } catch (SimpleCacheException $e) {
            throw $e;
        } catch (Psr6CacheException $e) {
            throw new InvalidArgumentException($e->getMessage(), $e->getCode(), $e);
        }

        return $item->isHit() ? $item->get() : $default;
    }

    /**
     * @inheritDoc
     *
     * @throws SimpleCacheException
     */
    public function set($key, $value, $ttl = null)
    {
        try {
            $item = $this->pool->getItem($key)->set($value);
        } catch (SimpleCacheException $e) {
            throw $e;
        } catch (Psr6CacheException $e) {
            throw new InvalidArgumentException($e->getMessage(), $e->getCode(), $e);
        }
        if (null !== $ttl) {
            $item->expiresAfter($ttl);
        }

        return $this->pool->save($item);
    }

    /**
     * @inheritDoc
     */
    public function delete($key)
    {
        try {
            return $this->pool->deleteItem($key);
        } catch (SimpleCacheException $e) {
            throw $e;
        } catch (Psr6CacheException $e) {
            throw new InvalidArgumentException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function clear(): bool
    {
        return $this->pool->clear();
    }

    /**
     * @inheritDoc
     *
     * @throws SimpleCacheException
     */
    public function getMultiple($keys, $default = null)
    {
        if ($keys instanceof Traversable) {
            $keys = iterator_to_array($keys, false);
        } elseif (!is_array($keys)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Cache keys must be array or Traversable, "%s" given',
                    is_object($keys) ? get_class($keys) : gettype($keys)
                )
            );
        }
        try {
            $items = $this->pool->getItems($keys);
        } catch (SimpleCacheException $e) {
            throw $e;
        } catch (Psr6CacheException $e) {
            throw new InvalidArgumentException($e->getMessage(), $e->getCode(), $e);
        }
        $values = [];
        /** @var CacheItem $item */
        foreach ($items as $key => $item) {
            $values[$key] = $item->isHit() ? $item->get() : $default;
        }

        return $values;
    }

    /**
     * @inheritDoc
     *
     * @throws SimpleCacheException
     */
    public function setMultiple($values, $ttl = null): bool
    {
        $valuesIsArray = is_array($values);
        if (!$valuesIsArray && !$values instanceof Traversable) {
            throw new InvalidArgumentException(
                sprintf(
                    'Cache values must be array or Traversable, "%s" given',
                    is_object($values) ? get_class($values) : gettype($values)
                )
            );
        }
        $items = [];
        try {
            if ($valuesIsArray) {
                $items = [];
                foreach ($values as $key => $value) {
                    $items[] = (string)$key;
                }
                $items = $this->pool->getItems($items);
            } else {
                foreach ($values as $key => $value) {
                    if (is_int($key)) {
                        $key = (string)$key;
                    }
                    $items[$key] = $this->pool->getItem($key)->set($value);
                }
            }
        } catch (SimpleCacheException $e) {
            throw $e;
        } catch (Psr6CacheException $e) {
            throw new InvalidArgumentException($e->getMessage(), $e->getCode(), $e);
        }

        $success = true;
        foreach ($items as $key => $item) {
            if ($valuesIsArray) {
                $item->set($values[$key]);
            }
            if (null !== $ttl) {
                $item->expiresAfter($ttl);
            }
            $success = $this->pool->saveDeferred($item) && $success;
        }

        return $this->pool->commit() && $success;
    }

    /**
     * @inheritDoc
     *
     * @throws SimpleCacheException
     */
    public function deleteMultiple($keys): bool
    {
        if ($keys instanceof Traversable) {
            $keys = iterator_to_array($keys, false);
        } elseif (!is_array($keys)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Cache keys must be array or Traversable, "%s" given',
                    is_object($keys) ? get_class($keys) : gettype($keys)
                )
            );
        }
        try {
            return $this->pool->deleteItems($keys);
        } catch (SimpleCacheException $e) {
            throw $e;
        } catch (Psr6CacheException $e) {
            throw new InvalidArgumentException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function has($key): bool
    {
        try {
            return $this->pool->hasItem($key);
        } catch (SimpleCacheException $e) {
            throw $e;
        } catch (Psr6CacheException $e) {
            throw new InvalidArgumentException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
