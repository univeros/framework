<?php
namespace Altair\Cache\Contracts;

interface CacheItemPoolAdapterInterface
{
    /**
     * Returns the maximum length of an id that an adapter supports. If null, the key can be of any size.
     *
     * @return int|null
     */
    public function getMaxIdLength(): ?int;

    /**
     * Returns a traversable set of cache items.
     *
     * @param string[] $keys An indexed array of keys of items to retrieve.
     *
     * @return array a collection of Cache Items keyed by the cache keys of each item. A Cache
     * item will be returned for each key, even if that key is not found. However, if no keys are specified then an
     * empty array MUST be returned instead.
     */
    public function getItems(array $keys = []): array;

    /**
     * Confirms if the cache contains specified cache item.
     *
     * Note: This method MAY avoid retrieving the cached value for performance reasons. This could
     * result in a race condition with CacheItemInterface::get(). To avoid such situation use
     * CacheItemInterface::isHit() instead.
     *
     * @param string $key The key for which to check existence.
     *
     * @return bool True if item exists in the cache, false otherwise.
     */
    public function hasItem(string $key): bool;

    /**
     * Deletes all items in the pool.
     *
     * @return bool True if the pool was successfully cleared. False if there was an error.
     */
    public function clear(): bool;

    /**
     * Removes multiple items from the pool.
     *
     * @param string[] $keys
     *          An array of keys that should be removed from the pool.
     *
     * @throws \Altair\Cache\Exception\InvalidArgumentException If any of the keys in $keys are not a legal value a
     * \Psr\Cache\InvalidArgumentException MUST be thrown.
     *
     * @return bool True if the items were successfully removed. False if there was an error.
     */
    public function deleteItems(array $keys): bool;

    /**
     * Persists a cache item immediately.
     *
     * @param array $values The cache item to save.
     * @param int $lifespan The time of the cached values or 0 for manual cleaning.
     *
     * @return bool|array True if the items were successfully persisted, otherwise will return an array with failed
     * items.
     */
    public function save(array $values, int $lifespan): bool;
}
