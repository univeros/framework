<?php
namespace Altair\Cache\Adapter;


use Altair\Cache\Contracts\CacheItemPoolAdapterInterface;

class NullCacheItemPoolAdapter implements CacheItemPoolAdapterInterface
{
    public function getMaxIdLength(): ?int
    {
        // TODO: Implement getMaxIdLength() method.
    }

    public function getItems(array $keys = []): array
    {
        // TODO: Implement getItems() method.
    }

    public function hasItem(string $key): bool
    {
        // TODO: Implement hasItem() method.
    }

    public function clear(): bool
    {
        // TODO: Implement clear() method.
    }

    public function deleteItems(array $keys): bool
    {
        // TODO: Implement deleteItems() method.
    }

    public function save(array $values, int $lifespan): bool
    {
        // TODO: Implement save() method.
    }

}
