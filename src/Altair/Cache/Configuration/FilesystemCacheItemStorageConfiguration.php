<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Cache\Configuration;

use Altair\Cache\Contracts\CacheItemStorageInterface;
use Altair\Cache\Storage\FilesystemCacheItemStorage;
use Altair\Configuration\Contracts\ConfigurationInterface;
use Altair\Configuration\Traits\EnvAwareTrait;
use Altair\Container\Container;
use Override;

class FilesystemCacheItemStorageConfiguration implements ConfigurationInterface
{
    use EnvAwareTrait;

    #[Override]
    public function apply(Container $container): void
    {
        $container->bind(FilesystemCacheItemStorage::class)->withParameters([
            'directory' => $this->env->get('CACHE_FS_DIRECTORY', sys_get_temp_dir() . '/altair-cache'),
        ]);
        $container->factory(
            CacheItemStorageInterface::class,
            static fn(Container $c): CacheItemStorageInterface => $c->make(FilesystemCacheItemStorage::class),
        );
    }
}
