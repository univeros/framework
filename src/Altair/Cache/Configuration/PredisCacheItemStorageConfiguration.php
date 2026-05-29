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
use Altair\Cache\Storage\PredisCacheItemStorage;
use Altair\Configuration\Contracts\ConfigurationInterface;
use Altair\Configuration\Traits\EnvAwareTrait;
use Altair\Container\Container;
use Override;
use Predis\Client;

class PredisCacheItemStorageConfiguration implements ConfigurationInterface
{
    use EnvAwareTrait;

    #[Override]
    public function apply(Container $container): void
    {
        $factory = function (): PredisCacheItemStorage {
            $client = new Client(
                [
                    'host' => $this->env->get('CACHE_REDIS_HOST', 'localhost'),
                    'port' => $this->env->get('CACHE_REDIS_PORT', 6379),
                ]
            );

            return new PredisCacheItemStorage($client);
        };

        $container->factory(PredisCacheItemStorage::class, $factory);
        $container->factory(
            CacheItemStorageInterface::class,
            static fn(Container $c): CacheItemStorageInterface => $c->get(PredisCacheItemStorage::class),
        );
    }
}
