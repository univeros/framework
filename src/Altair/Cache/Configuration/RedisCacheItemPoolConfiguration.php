<?php
namespace Altair\Cache\Configuration;

use Altair\Cache\Adapter\RedisCacheItemPoolAdapter;
use Altair\Cache\Contracts\CacheItemPoolAdapterInterface;
use Altair\Configuration\Contracts\ConfigurationInterface;
use Altair\Configuration\Traits\EnvAwareTrait;
use Altair\Container\Container;
use Predis\Client;

class RedisCacheItemPoolConfiguration implements ConfigurationInterface
{
    use EnvAwareTrait;

    public function apply(Container $container)
    {
        $factory = function () {
            $client = new Client(
                [
                    'host' => $this->env->get('CACHE_REDIS_HOST', 'localhost'),
                    'port' => $this->env->get('CACHE_REDIS_PORT', 6379)
                ]
            );

            return new RedisCacheItemPoolAdapter($client);
        };

        $container
            ->delegate(RedisCacheItemPoolAdapter::class, $factory)
            ->alias(CacheItemPoolAdapterInterface::class, RedisCacheItemPoolAdapter::class);
    }
}
