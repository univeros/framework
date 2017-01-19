<?php
namespace Altair\Cache\Contracts;

use Psr\Cache\CacheItemInterface;

interface TagAwareCacheItemInterface extends CacheItemInterface
{
    /**
     * Returns a cloned instance with new specified tags.
     *
     * @param array ...$tags
     *
     * @return TagAwareCacheItemInterface
     */
    public function withTags(...$tags): TagAwareCacheItemInterface;
}
