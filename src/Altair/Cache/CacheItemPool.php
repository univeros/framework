<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Cache;

use Altair\Cache\Contracts\CacheItemKeyValidatorInterface;
use Altair\Cache\Contracts\CacheItemStorageInterface;
use Altair\Cache\Exception\InvalidArgumentException;
use Altair\Cache\Storage\PredisCacheItemStorage;
use Altair\Cache\Validator\CacheItemKeyValidator;
use Closure;
use Exception;
use Generator;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

class CacheItemPool implements CacheItemPoolInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    protected $store;
    protected $cacheItemKeyValidator;
    protected $namespace;
    protected $deferred = [];
    protected $cacheItemFactory;
    protected $deferredMergerClosure;

    /**
     * CacheItemPool constructor.
     *
     * @param CacheItemStorageInterface $store
     * @param string $namespace
     * @param int $defaultLifespan
     * @param CacheItemKeyValidatorInterface|null $cacheItemKeyValidator
     */
    public function __construct(
        CacheItemStorageInterface $store,
        string $namespace = '',
        int $defaultLifespan = 0,
        CacheItemKeyValidatorInterface $cacheItemKeyValidator = null
    ) {
        $this->store = $store;

        if ($this->store instanceof PredisCacheItemStorage) {
            $this->store->useNamespace($namespace);
        }

        $this->cacheItemKeyValidator = $cacheItemKeyValidator ?? new CacheItemKeyValidator();
        $this->namespace = empty($namespace) ? '' : $this->makeId($namespace) . ':';

        $this->cacheItemFactory = $this->createCacheItemFactoryClosure($defaultLifespan);
        $this->deferredMergerClosure = $this->createDeferredMergerClosure();
    }

    /**
     * Ensure all deferred cache items are saved.
     */
    public function __destruct()
    {
        $this->ensureCommitDeferred();
    }

    /**
     * @inheritdoc
     */
    public function getItem($key)
    {
        foreach ($this->getItems([$key]) as $item) {
            return $item;
        }
    }

    /**
     * @inheritdoc
     */
    public function getItems(array $keys = [])
    {
        $this->ensureCommitDeferred();
        $ids = array_map(
            function ($key) {
                return $this->makeId($key);
            },
            $keys
        );
        try {
            $items = $this->store->getItems($ids);
        } catch (Exception $e) {
            $this->log('Failed to fetch requested cache items.', ['keys' => $keys, 'exception' => $e]);
            $items = [];
        }

        return $this->createCacheItemsGenerator($items, array_combine($ids, $keys));
    }

    /**
     * @inheritdoc
     */
    public function hasItem($key): bool
    {
        $id = $this->makeId($key);

        if (isset($this->deferred[$id])) {
            $this->commit();
        }
        try {
            return $this->store->hasItem($id);
        } catch (Exception $e) {
            $this->log(
                'Failed to check whether and item with key ":key" is cached.',
                ['key' => $key, 'exception' => $e]
            );
        }

        return false;
    }

    /**
     * @inheritdoc
     */
    public function clear(): bool
    {
        $this->deferred = [];

        try {
            return $this->store->clear();
        } catch (Exception $e) {
            $this->log('Failed clear the cache.', ['exception' => $e]);
        }

        return false;
    }

    /**
     * @inheritdoc
     */
    public function deleteItem($key): bool
    {
        return $this->deleteItems([$key]);
    }

    /**
     * @inheritdoc
     */
    public function deleteItems(array $keys): bool
    {
        $ids = [];
        foreach ($keys as $key) {
            $ids[$key] = $this->makeId($key);
            unset($this->deferred[$key]);
        }

        try {
            if ($this->store->deleteItems($ids)) {
                return true;
            }
        } catch (Exception $e) {
        }

        return $this->retryDeleteItems($ids);
    }

    /**
     * @inheritdoc
     */
    public function save(CacheItemInterface $item): bool
    {
        if (!$this->saveDeferred($item)) {
            return false;
        }

        return $this->commit();
    }

    /**
     * @inheritdoc
     */
    public function saveDeferred(CacheItemInterface $item): bool
    {
        if (!$item instanceof CacheItem) {
            return false;
        }
        $this->deferred[$item->getKey()] = $item;

        return true;
    }

    /**
     * @inheritdoc
     */
    public function commit(): bool
    {
        $success = true;
        [$merged, $expired] = call_user_func(
            $this->deferredMergerClosure,
            $this->deferred,
            $this->namespace
        );
        $retry = $this->deferred = [];
        if (!empty($expired)) {
            $this->store->deleteItems($expired);
        }
        foreach ($merged as $lifespan => $values) {
            try {
                if (($e = $this->store->save($values, $lifespan)) === true) {
                    continue;
                }
            } catch (Exception $e) {
            }
            if (is_array($e) || 1 === count($values)) {
                foreach (is_array($e) ? $e : array_keys($values) as $id) {
                    $success = false;
                    $value = $values[$id];
                    $this->log(
                        'Failed to save cache item with key ":key" (:type)',
                        [
                            'key' => substr($id, strlen($this->namespace)),
                            'type' => is_object($value) ? get_class($value) : gettype($value),
                            'exception' => $e instanceof Exception ? $e : null,
                        ]
                    );
                }
            } else { // retry
                foreach (array_keys($values) as $id) {
                    $retry[$lifespan][] = $id;
                }
            }
        }

        return $success && $this->retryCommit($merged, $retry);
    }

    /**
     * When bulk delete has failed, retry them individually.
     *
     * @param array $ids
     *
     * @return bool
     */
    protected function retryDeleteItems(array $ids): bool
    {
        $success = true;
        foreach ($ids as $key => $id) {
            try {
                $e = null;
                if ($this->store->deleteItems([$id])) {
                    continue;
                }
            } catch (Exception $e) {
            }
            $this->log('Failed to delete cache item with key ":key".', ['key' => $key, 'exception' => $e]);
            $success = false;
        }

        return $success;
    }

    /**
     * When doing bulk save, if it has failed, retry failed individually.
     *
     * @param array $merged
     * @param array $data
     *
     * @return bool
     */
    protected function retryCommit(array $merged, array $data): bool
    {
        $success = true;

        foreach ($data as $lifespan => $ids) {
            foreach ($ids as $id) {
                try {
                    $value = $merged[$lifespan][$id];
                    if (($e = $this->store->save([$id => $value], $lifespan)) === true) {
                        continue;
                    }
                } catch (Exception $e) {
                }
                $success = false;
                $this->log(
                    'Failed to save cache item with key ":key" (:type)',
                    [
                        'key' => substr($id, strlen($this->namespace)),
                        'type' => is_object($value) ? get_class($value) : gettype($value),
                        'exception' => $e instanceof Exception ? $e : null,
                    ]
                );
            }
        }

        return $success;
    }

    /**
     * If there are items postponed to be saved, save them.
     */
    protected function ensureCommitDeferred()
    {
        if (!empty($this->deferred)) {
            $this->commit();
        }
    }

    /**
     * Generates the cache items returning a generator.
     *
     * @param array $items
     * @param array $keys
     *
     * @return \Generator
     */
    protected function createCacheItemsGenerator(array $items, array $keys): ?Generator
    {
        try {
            foreach ($items as $id => $value) {
                $key = $keys[$id];
                unset($keys[$id]);
                yield $key => call_user_func($this->cacheItemFactory, $key, $value, true);
            }
        } catch (Exception $e) {
            $this->log('Failed to fetch requested items', ['keys' => array_values($keys), 'exception' => $e]);
        }
        foreach ($keys as $key) {
            yield $key => call_user_func($this->cacheItemFactory, $key, null, false);
        }
    }

    /**
     * Creates the cache item factory closure by using the Closure Bind Override. It uses the bind static method of
     * Closure to access the protected properties of the object.
     *
     * @param int|null $defaultLifespan
     *
     * @return Closure
     */
    protected function createCacheItemFactoryClosure(int $defaultLifespan = null): Closure
    {
        return Closure::bind(
            function (string $key, $value, bool $isHit) use ($defaultLifespan) {
                $cacheItem = new CacheItem();
                $cacheItem->{'key'} = $key;
                $cacheItem->{'value'} = $value;
                $cacheItem->{'isHit'} = $isHit;
                $cacheItem->{'defaultLifespan'} = $defaultLifespan;

                return $cacheItem;
            },
            null,
            CacheItem::class
        );
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
            function (array $deferred, string $namespace) {
                $merged = $expired = [];
                $now = time();

                foreach ($deferred as $key => $item) {
                    if (null === $item->expirationTime) {
                        $defaultExpirationTime = $item->defaultLifespan > 0 ? $item->defaultLifespan : 0;
                        $merged[$defaultExpirationTime][$namespace . $key] = $item->value;
                    } elseif ($item->expirationTime > $now) {
                        $merged[$item->expirationTime - $now][$namespace . $key] = $item->value;
                    } else {
                        $expired[] = $namespace . $key;
                    }
                }

                return [$merged, $expired];
            },
            null,
            CacheItem::class
        );
    }

    /**
     * Makes a cache key injecting the namespace if any.
     *
     * @param string $key
     *
     * @return string
     */
    protected function makeId(string $key): string
    {
        if (!$this->cacheItemKeyValidator->validate($key)) {
            throw new InvalidArgumentException($this->cacheItemKeyValidator->getFailureReason());
        }

        if (null === $this->store->getMaxIdLength()) {
            return $this->namespace . $key;
        }
        $id = $this->namespace . $key;

        return strlen($id) > $this->store->getMaxIdLength()
            ? $this->namespace . substr_replace(base64_encode(hash('sha256', $key, true)), ':', -22)
            : $id;
    }

    /**
     * Logging helper function. If no logger has been set, then a warning error will be triggered having
     * previously replaced the tokens on the error message (i.e. ':key') for its correspondent value in the context.
     *
     * @param string $message
     * @param array $context
     */
    protected function log(string $message, array $context = [])
    {
        if (null !== $this->logger) {
            $this->logger->warning($message, $context);
        } else {
            $replace_pairs = [];
            foreach ($context as $key => $value) {
                if (is_scalar($value)) {
                    $replace[':' . $key] = $value;
                }
            }
            @trigger_error(strtr($message, $replace_pairs), E_USER_WARNING);
        }
    }
}
