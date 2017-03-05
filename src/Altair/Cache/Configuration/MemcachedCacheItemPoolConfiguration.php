<?php
namespace Altair\Cache\Configuration;

use Altair\Cache\Adapter\MemcachedCacheItemPoolAdapter;
use Altair\Cache\Contracts\CacheItemPoolAdapterInterface;
use Altair\Configuration\Contracts\ConfigurationInterface;
use Altair\Container\Container;

class MemcachedCacheItemPoolConfiguration implements ConfigurationInterface
{
    public function apply(Container $container)
    {
        $container
            ->alias(CacheItemPoolAdapterInterface::class, MemcachedCacheItemPoolAdapter::class);
    }
}
