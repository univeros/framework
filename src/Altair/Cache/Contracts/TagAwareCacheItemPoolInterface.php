<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Cache\Contracts;

use Psr\Cache\CacheItemPoolInterface;

interface TagAwareCacheItemPoolInterface extends CacheItemPoolInterface
{
    /**
     * Invalidates cached items using a tag.
     *
     * @param string $tag a tag to invalidate
     *
     * @throws \Altair\Cache\Exception\InvalidArgumentException when $tag is not valid
     * @return bool true on success, false otherwise
     *
     */
    public function invalidateTag(string $tag): bool;

    /**
     * Invalidates cached items using tags.
     *
     * @param string[] $tags An array of tags to invalidate
     *
     * @throws \Altair\Cache\Exception\InvalidArgumentException when $tags is not valid
     * @return bool true on success, false otherwise
     *
     */
    public function invalidateTags(array $tags): bool;

    /**
     * {@inheritDoc}
     *
     * @return TagAwareCacheItemInterface
     */
    public function getItem($key): TagAwareCacheItemInterface;

    /**
     * {@inheritDoc}
     *
     * @return array|\Traversable|TagAwareCacheItemInterface[]
     */
    public function getItems(array $keys = []);
}
