<?php
namespace Altair\Cache\Adapter;

use Altair\Cache\Contracts\CacheItemPoolAdapterInterface;

class NullCacheItemPoolAdapter implements CacheItemPoolAdapterInterface
{
    public function getMaxIdLength(): ?int
    {
        return null;
    }

    public function getItems(array $keys = []): array
    {
        return [];
    }

    public function hasItem(string $key): bool
    {
        return false;
    }

    public function clear(): bool
    {
        return true;
    }

    public function deleteItems(array $keys): bool
    {
        return true;
    }

    public function save(array $values, int $lifespan): bool
    {
        return false;
    }
}
