<?php
namespace Altair\Cache\Pool;

use Altair\Cache\Contracts\TagAwareCacheItemPoolInterface;

class TaggableCacheItemPool extends AbstractCacheItemPool implements TagAwareCacheItemPoolInterface
{
    public function invalidateTag(string $tag): bool
    {
        // TODO: Implement invalidateTag() method.
    }

    public function invalidateTags(...$tags): bool
    {
        // TODO: Implement invalidateTags() method.
    }
}
