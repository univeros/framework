<?php
namespace Altair\Cache\Contracts;

use Psr\Cache\CacheItemPoolInterface;

interface TagAwareCacheItemPoolInterface extends CacheItemPoolInterface
{
    /**
     * Invalidates cached items using a tag.
     *
     * @param string $tag a tag to invalidate
     *
     * @return bool true on success, false otherwise
     *
     * @throws \Altair\Cache\Exception\InvalidArgumentException when $tag is not valid
     */
    public function invalidateTag(string $tag): bool;

    /**
     * Invalidates cached items using tags.
     *
     * @param string[] $tags An array of tags to invalidate
     *
     * @return bool true on success, false otherwise
     *
     * @throws \Altair\Cache\Exception\InvalidArgumentException when $tags is not valid
     */
    public function invalidateTags(array $tags): bool;

    /**
     * {@inheritdoc}
     *
     * @return TagAwareCacheItemInterface
     */
    public function getItem($key);

    /**
     * {@inheritdoc}
     *
     * @return array|\Traversable|TagAwareCacheItemInterface[]
     */
    public function getItems(array $keys = []);
}
