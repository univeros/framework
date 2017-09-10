<?php
namespace Altair\Cache\Configuration;

use Altair\Cache\Contracts\CacheItemStorageInterface;
use Altair\Cache\Storage\FilesystemCacheItemStorage;
use Altair\Configuration\Contracts\ConfigurationInterface;
use Altair\Configuration\Traits\EnvAwareTrait;
use Altair\Container\Container;
use Altair\Container\Definition;

class FilesystemCacheItemStorageConfiguration implements ConfigurationInterface
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
            ->define(FilesystemCacheItemStorage::class, $adapterConfiguration)
            ->alias(CacheItemStorageInterface::class, FilesystemCacheItemStorage::class);
    }
}
