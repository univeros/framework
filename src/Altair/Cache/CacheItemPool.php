<?php
namespace Altair\Cache;

use Altair\Cache\Contracts\CacheItemKeyValidatorInterface;
use Altair\Cache\Contracts\CacheItemPoolAdapterInterface;
use Altair\Cache\Contracts\TagAwareCacheItemPoolInterface;
use Altair\Cache\Exception\InvalidArgumentException;
use Closure;
use Psr\Cache\CacheItemInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

class CacheItemPool implements TagAwareCacheItemPoolInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    protected $adapter;
    protected $cacheItemKeyValidator;
    protected $namespace;
    protected $deferred = [];
    protected $cacheItemFactory;
    protected $deferredMergerClosure;

    public function __construct(
        CacheItemPoolAdapterInterface $adapter,
        CacheItemKeyValidatorInterface $cacheItemKeyValidator,
        string $namespace = '',
        int $defaultExpirationTime = 0
    ) {
        $this->adapter = $adapter;
        $this->cacheItemKeyValidator = $cacheItemKeyValidator;
        $this->namespace = $namespace === '' ? '' : $this->getId($namespace) . ':';
        $this->cacheItemFactory = $this->createCacheItemFactoryClosure($defaultExpirationTime);
        $this->deferredMergerClosure = $this->createDeferredMergerClosure();
    }

    public function getItem($key)
    {
        if ($this->deferred) {
            $this->commit();
        }
    }

    public function getItems(array $keys = [])
    {
        // TODO: Implement getItems() method.
    }

    public function hasItem($key)
    {
        // TODO: Implement hasItem() method.
    }

    public function clear()
    {
        // TODO: Implement clear() method.
    }

    public function deleteItem($key)
    {
        // TODO: Implement deleteItem() method.
    }

    public function deleteItems(array $keys)
    {
        // TODO: Implement deleteItems() method.
    }

    public function save(CacheItemInterface $item)
    {
        // TODO: Implement save() method.
    }

    public function saveDeferred(CacheItemInterface $item)
    {
        // TODO: Implement saveDeferred() method.
    }

    public function commit()
    {
        // TODO: Implement commit() method.
    }

    public function invalidateTag(string $tag): bool
    {
        // TODO: Implement invalidateTag() method.
    }

    public function invalidateTags(array $tags): bool
    {
        // TODO: Implement invalidateTags() method.
    }

    protected function getId(string $key): string
    {
        $reason = '';
        if (!$this->cacheItemKeyValidator->validate($key, $reason)) {
            throw new InvalidArgumentException($reason);
        }
        if (null === $this->adapter->getMaxIdLength()) {
            return $this->namespace . $key;
        }
        $id = $this->namespace . $key;

        return strlen($id) > $this->adapter->getMaxIdLength()
            ? $this->namespace . substr_replace(base64_encode(hash('sha256', $key, true)), ':', -22)
            : $id;
    }

    /**
     * Creates the cache item factory closure by using the Closure Bind Override. It uses the bind static method of
     * Closure to access the protected properties of the object.
     *
     * @param int $defaultExpirationTime
     *
     * @return Closure
     */
    protected function createCacheItemFactoryClosure(int $defaultExpirationTime): Closure
    {
        return function (string $key, $value, bool $isHit) use ($defaultExpirationTime) {
            $cacheItem = new CacheItem();
            $cacheItem->{'key'} = $key;
            $cacheItem->{'value'} = $value;
            $cacheItem->{'isHit'} = $isHit;
            $cacheItem->{'defaultExpirationTime'} = $defaultExpirationTime;

            return $cacheItem;
        };
    }

    /**
     * Creates the cache item merger by expiring time closure. Again, we are using the Closure Bind Override method to
     * be able to modify the CacheItem instances on deferred. Are the proposed CacheItemInterface a bit too weak ?
     *
     * @return Closure
     */
    protected function createDeferredMergerClosure(): Closure
    {
        return Closure::bind(
            function (array $deferred, string $namespace, array &$expired) {
                $merged = [];
                $now = time();

                foreach ($deferred as $key => $item) {
                    if (null === $item->expirationTime) {
                        $defaultExpirationTime = $item->defaultExpirationTime > 0 ? $item->defaultExpirationTime : 0;
                        $merged[$defaultExpirationTime][$namespace . $key] = $item->value;
                    } elseif ($item->expirationTime > $now) {
                        $merged[$item->expirationTime - $now][$namespace . $key] = $item->value;
                    } else {
                        $expired[] = $namespace . $key;
                    }
                }

                return $merged;
            },
            null,
            CacheItem::class
        );
    }
}
