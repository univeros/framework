<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Filesystem\Configuration;

use Altair\Configuration\Contracts\ConfigurationInterface;
use Altair\Configuration\Traits\EnvAwareTrait;
use Altair\Container\Container;
use League\Flysystem\AdapterInterface;
use League\Flysystem\Cached\CachedAdapter;
use League\Flysystem\Cached\Storage\Memory;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemInterface;

class FilesystemAdapterConfiguration implements ConfigurationInterface
{
    use EnvAwareTrait;

    public function apply(Container $container): void
    {
        // AdapterInterface configuration must be included *always*
        // before this configuration class. Other types of configuration
        // are allowed. Use this as an example to create your very own.
        if ($this->env->get('FS_USE_CACHE')) {
            // @see http://flysystem.thephpleague.com/caching/
            $container
                ->delegate(
                    FilesystemInterface::class,
                    function () use ($container) {
                        $adapter = $container->make(AdapterInterface::class);
                        $cachedAdapter = new CachedAdapter($adapter, new Memory());

                        return new Filesystem($cachedAdapter);
                    }
                );
        } else {
            $container->alias(FilesystemInterface::class, Filesystem::class);
        }
    }
}
