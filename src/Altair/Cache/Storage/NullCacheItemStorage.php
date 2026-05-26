<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Cache\Storage;

use Altair\Cache\Contracts\CacheItemStorageInterface;

class NullCacheItemStorage implements CacheItemStorageInterface
{
    /**
     * @inheritDoc
     */
    #[\Override]
    public function getMaxIdLength(): ?int
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function getItems(array $keys = []): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function hasItem(string $key): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function clear(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function deleteItems(array $keys): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function save(array $values, int $lifespan): bool
    {
        return false;
    }
}
