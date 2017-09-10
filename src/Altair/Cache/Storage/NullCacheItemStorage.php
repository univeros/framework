<?php
namespace Altair\Cache\Storage;

use Altair\Cache\Contracts\CacheItemStorageInterface;

class NullCacheItemStorage implements CacheItemStorageInterface
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
    public function save(array $values, int $lifespan)
    {
        return false;
    }
}
