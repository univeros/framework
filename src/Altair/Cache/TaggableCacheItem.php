<?php
namespace Altair\Cache;

use Altair\Cache\Contracts\TagAwareCacheItemInterface;

class TaggableCacheItem extends CacheItem implements TagAwareCacheItemInterface
{
    public function withTags(...$tags): TagAwareCacheItemInterface
    {
        // TODO: Implement withTags() method.
    }
}
