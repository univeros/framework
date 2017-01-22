<?php
namespace Altair\Cache\Adapter;

use Altair\Cache\Contracts\CacheItemPoolAdapterInterface;

class NullCacheItemPoolAdapter implements CacheItemPoolAdapterInterface
{
    /**
     * @inheritdoc
     */
    public function getMaxIdLength(): ?int
    {
        return null;
    }

    /**
     * @inheritdoc
     */
    public function getItems(array $keys = []): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function hasItem(string $key): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function clear(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function deleteItems(array $keys): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function save(array $values, int $lifespan): bool
    {
        return false;
    }
}
