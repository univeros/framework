<?php
namespace Altair\Cache;

use Altair\Cache\Contracts\CacheItemKeyValidatorInterface;
use Altair\Cache\Contracts\CacheItemPoolAdapterInterface;
use Altair\Cache\Contracts\CacheItemTagValidatorInterface;
use Altair\Cache\Exception\InvalidArgumentException;
use Altair\Cache\Validator\CacheItemKeyValidator;
use Altair\Cache\Validator\CacheItemTagValidator;
use Closure;
use Exception;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

class CacheItemPool implements CacheItemPoolInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    protected $adapter;
    protected $cacheItemKeyValidator;
    protected $namespace;
    protected $deferred = [];
    protected $cacheItemFactory;
    protected $deferredMergerClosure;

    /**
     * CacheItemPool constructor.
     *
     * @param CacheItemPoolAdapterInterface $adapter
     * @param string $namespace
     * @param int $defaultLifespan
     * @param CacheItemKeyValidatorInterface|null $cacheItemKeyValidator
     * @param CacheItemTagValidatorInterface|null $cacheItemTagValidator
     */
    public function __construct(
        CacheItemPoolAdapterInterface $adapter,
        string $namespace = '',
        int $defaultLifespan = 0,
        CacheItemKeyValidatorInterface $cacheItemKeyValidator = null,
        CacheItemTagValidatorInterface $cacheItemTagValidator = null
    ) {
        $this->adapter = $adapter;
        $this->cacheItemKeyValidator = $cacheItemKeyValidator?? new CacheItemKeyValidator();
        $this->namespace = $namespace === '' ? '' : $this->makeId($namespace) . ':';
        $this->cacheItemFactory = $this->createCacheItemFactoryClosure($defaultLifespan, $cacheItemTagValidator);
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
            $items = $this->adapter->getItems($ids);
        } catch (Exception $e) {
            $this->log('Failed to fetch requested items.', ['keys' => $keys, 'exception' => $e]);
            $items = [];
        }

        return $this->createCacheItemsGenerator($items, array_combine($ids, $keys));
    }

    /**
     * @inheritdoc
     */
    public function hasItem($key)
    {
        $id = $this->makeId($key);

        if (isset($this->deferred[$id])) {
            $this->commit();
        }
        try {
            return $this->adapter->hasItem($key);
        } catch (Exception $e) {
            $this->log(
                'Failed to check whether and item with key ":key" is cached.',
                ['key' => $key, 'exception' => $e]
            );

            return false;
        }
    }

    /**
     * @inheritdoc
     */
    public function clear()
    {
        $this->deferred = [];

        try {
            return $this->adapter->clear();
        } catch (Exception $e) {
            $this->log('Failed clear the cache.', ['exception' => $e]);

            return false;
        }
    }

    /**
     * @inheritdoc
     */
    public function deleteItem($key)
    {
        return $this->deleteItems([$key]);
    }

    /**
     * @inheritdoc
     */
    public function deleteItems(array $keys)
    {
        $ids = [];
        foreach ($keys as $key) {
            $ids[$key] = $this->makeId($key);
            unset($this->deferred[$key]);
        }

        try {
            if ($this->adapter->deleteItems($ids)) {
                return true;
            }
        } catch (Exception $e) {
        }

        return $this->retryDeleteItems($ids);
    }

    /**
     * @inheritdoc
     */
    public function save(CacheItemInterface $item)
    {
        if (!$this->saveDeferred($item)) {
            return false;
        }

        return $this->commit();
    }

    /**
     * @inheritdoc
     */
    public function saveDeferred(CacheItemInterface $item)
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
    public function commit()
    {
        $result = true;
        $expired = [];
        $merged = call_user_func($this->deferredMergerClosure, [$this->deferred, $this->namespace, $expired]);
        $retry = $this->deferred = [];
        if (!empty($expired)) {
            $this->adapter->deleteItems($expired);
        }
        foreach ($merged as $lifespan => $values) {
            try {
                if (($e = $this->adapter->save($values, $lifespan)) === true) {
                    continue;
                }
            } catch (Exception $e) {
            }
            if (is_array($e) || 1 === count($values)) {
                foreach (is_array($e) ? $e : array_keys($values) as $id) {
                    $result = false;
                    $value = $values[$id];
                    $this->log(
                        'Failed to save cache item with key ":key" (:type)',
                        [
                            'key' => substr($id, strlen($this->namespace)),
                            'type' => is_object($value) ? get_class($value) : gettype($value),
                            'exception' => $e instanceof Exception ? $e : null
                        ]
                    );
                }
            } else { // retry
                foreach (array_keys($values) as $id) {
                    $retry[$lifespan][] = $id;
                }
            }
        }

        $this->retryCommit($merged, $retry, $result);
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
        $result = true;
        foreach ($ids as $key => $id) {
            try {
                $e = null;
                if ($this->adapter->deleteItems([$id])) {
                    continue;
                }
            } catch (Exception $e) {
            }
            $this->log('Failed to delete cache item with key ":key".', ['key' => $key, 'exception' => $e]);
            $result = false;
        }

        return $result;
    }

    /**
     * When doing bulk save, if it has failed, retry failed individually.
     *
     * @param array $merged
     * @param array $data
     * @param bool $result
     *
     * @return bool
     */
    protected function retryCommit(array $merged, array $data, bool &$result): bool
    {
        foreach ($data as $lifespan => $ids) {
            foreach ($ids as $id) {
                try {
                    $value = $merged[$lifespan][$id];
                    if (($e = $this->adapter->save([$id => $value], $lifespan) === true)) {
                        continue;
                    }
                } catch (Exception $e) {
                }
                $result = false;
                $this->log(
                    'Failed to save cache item with key ":key" (:type)',
                    [
                        'key' => substr($id, strlen($this->namespace)),
                        'type' => is_object($value) ? get_class($value) : gettype($value),
                        'exception' => $e instanceof Exception ? $e : null
                    ]
                );
            }
        }

        return $result;
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
    protected function createCacheItemsGenerator(array $items, array $keys)
    {
        try {
            foreach ($items as $id => $value) {
                $key = $keys[$id];
                unset($keys[$id]);
                yield $key => call_user_func_array($this->cacheItemFactory, [$key, $value, true]);
            }
        } catch (Exception $e) {
            $this->log('Failed to fetch requested items', ['keys' => array_values($keys), 'exception' => $e]);
        }
        foreach ($keys as $key) {
            yield $key => call_user_func_array($this->cacheItemFactory, [$key, null, false]);
        }
    }

    /**
     * Creates the cache item factory closure by using the Closure Bind Override. It uses the bind static method of
     * Closure to access the protected properties of the object.
     *
     * @param int $defaultLifespan
     * @param CacheItemTagValidatorInterface $cacheItemTagValidator
     *
     * @return Closure
     */
    protected function createCacheItemFactoryClosure(
        int $defaultLifespan,
        CacheItemTagValidatorInterface $cacheItemTagValidator
    ): Closure {
        return function (string $key, $value, bool $isHit) use ($defaultLifespan, $cacheItemTagValidator) {
            $cacheItem = new CacheItem();
            $cacheItem->{'key'} = $key;
            $cacheItem->{'value'} = $value;
            $cacheItem->{'isHit'} = $isHit;
            $cacheItem->{'defaultLifespan'} = $defaultLifespan;
            $cacheItem->{'cacheItemTagValidator'} = $cacheItemTagValidator?? new CacheItemTagValidator();

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
                        $defaultExpirationTime = $item->defaultLifespan > 0 ? $item->defaultLifespan : 0;
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

    /**
     * Logging helper function. If no logger has been set, then a warning error will be triggered having
     * previously replaced the tokens on the error message (i.e. ':key') for its correspondent value in the context.
     *
     * @param string $message
     * @param array $context
     */
    protected function log(string $message, array $context = [])
    {
        if ($this->logger) {
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
