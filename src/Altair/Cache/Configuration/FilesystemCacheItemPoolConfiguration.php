<?php
namespace Altair\Cache\Configuration;

use Altair\Cache\Adapter\FilesystemCacheItemPoolAdapter;
use Altair\Cache\Contracts\CacheItemPoolAdapterInterface;
use Altair\Configuration\Contracts\ConfigurationInterface;
use Altair\Configuration\Traits\EnvAwareTrait;
use Altair\Container\Container;
use Altair\Container\Definition;

class FilesystemCacheItemPoolConfiguration implements ConfigurationInterface
{
    use EnvAwareTrait;

    public function apply(Container $container)
    {
        $adapterConfiguration = new Definition(
            [
                ':directory' => $this->env->get('CACHE_FS_DIRECTORY', sys_get_temp_dir() . '/altair-cache')
            ]
        );

        $container
            ->define(FilesystemCacheItemPoolAdapter::class, $adapterConfiguration)
            ->alias(CacheItemPoolAdapterInterface::class, FilesystemCacheItemPoolAdapter::class);
    }
}
