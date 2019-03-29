<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Cache\Configuration;

use Altair\Cache\Contracts\CacheItemStorageInterface;
use Altair\Cache\Storage\MemcachedCacheItemStorage;
use Altair\Configuration\Contracts\ConfigurationInterface;
use Altair\Configuration\Traits\EnvAwareTrait;
use Altair\Container\Container;
use Memcached;

class MemcachedCacheItemStorageConfiguration implements ConfigurationInterface
{
    use EnvAwareTrait;

    public function apply(Container $container): void
    {
        $factory = function () {
            $memcached = new Memcached();
            $memcached->addServers([
                [
                    $this->env->get('CACHE_MEMCACHED_HOST', 'localhost'),
                    $this->env->get('CACHE_MEMCACHED_PORT', 11211),
                    // $this->nv->get('CACHE_MEMCACHED_WEIGHT', 1)
                ]
            ]);

            return $memcached;
        };

        $container
            ->delegate(MemcachedCacheItemStorage::class, $factory)
            ->alias(CacheItemStorageInterface::class, MemcachedCacheItemStorage::class);
    }
}
