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
use Altair\Cache\Storage\RedisCacheItemStorage;
use Altair\Configuration\Contracts\ConfigurationInterface;
use Altair\Configuration\Traits\EnvAwareTrait;
use Altair\Container\Container;
use Override;
use Redis;

/**
 * Wires the ext-redis backed CacheItemStorageInterface implementation. For the userland
 * Predis library, use {@see PredisCacheItemStorageConfiguration}.
 */
class RedisCacheItemStorageConfiguration implements ConfigurationInterface
{
    use EnvAwareTrait;

    #[Override]
    public function apply(Container $container): void
    {
        $factory = function (): RedisCacheItemStorage {
            $client = new Redis();
            $client->connect(
                (string) $this->env->get('CACHE_REDIS_HOST', 'localhost'),
                (int) $this->env->get('CACHE_REDIS_PORT', 6379),
            );

            $password = $this->env->get('CACHE_REDIS_PASSWORD');
            if (\is_string($password) && $password !== '') {
                $client->auth($password);
            }

            $database = $this->env->get('CACHE_REDIS_DATABASE');
            if ($database !== null && $database !== '') {
                $client->select((int) $database);
            }

            return new RedisCacheItemStorage(
                $client,
                (string) $this->env->get('CACHE_REDIS_NAMESPACE', ''),
            );
        };

        $container->factory(RedisCacheItemStorage::class, $factory);
        $container->factory(
            CacheItemStorageInterface::class,
            static fn(Container $c): CacheItemStorageInterface => $c->get(RedisCacheItemStorage::class),
        );
    }
}
