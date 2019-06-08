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
    public function getMaxIdLength(): ?int
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function getItems(array $keys = []): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function hasItem(string $key): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function clear(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function deleteItems(array $keys): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function save(array $values, int $lifespan)
    {
        return false;
    }
}
