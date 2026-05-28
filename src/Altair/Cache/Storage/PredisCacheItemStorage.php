<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Cache\Storage;

use Altair\Cache\Contracts\CacheItemStorageInterface;
use Altair\Cache\Exception\InvalidArgumentException;
use Altair\Cache\Support\CacheItemUnserializer;
use Altair\Cache\Traits\RedisNamespaceValidationAwareTrait;
use ErrorException;
use Exception;
use Override;
use Predis\Client;
use Predis\Collection\Iterator\Keyspace;
use Predis\Connection\Cluster\PredisCluster;
use Predis\Connection\Cluster\RedisCluster;

class PredisCacheItemStorage implements CacheItemStorageInterface
{
    use RedisNamespaceValidationAwareTrait;

    protected Client $client;

    protected string $namespace = '';

    /**
     * RedisCacheItemPoolStorage constructor.
     */
    public function __construct(Client $redis, string $namespace = '')
    {
        $info = $redis->info('Server');
        $info = $info['Server'] ?? $info;
        if (!version_compare($info['redis_version'], '2.8', '>=')) {
            throw new InvalidArgumentException(\sprintf('%s requires Redis 2.8 or above.', static::class));
        }

        $this->client = $redis;
        $this->useNamespace($namespace);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getMaxIdLength(): ?int
    {
        return null;
    }

    /**
     * @inheritDoc
     * @throws ErrorException
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function getItems(array $keys = []): array
    {
        $items = [];
        if ($keys !== []) {
            $values = $this->client->mget($keys);
            foreach ($keys as $index => $key) {
                if ($values[$index]) {
                    $items[$key] = CacheItemUnserializer::unserialize($values[$index]);
                }
            }
        }

        return $items;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function hasItem(string $key): bool
    {
        return (bool) $this->client->exists($key);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function clear(): bool
    {
        $success = true;
        $hosts = $this->getHosts($this->client);

        if (null === $hosts) {
            // When using a native Redis cluster, clearing the cache cannot work and always returns false.
            // Clearing the cache should then be done by any other means (e.g. by restarting the cluster).
            //
            // - Thanks Symfony ;)

            return false;
        }

        /** @var Client $host */
        foreach ($hosts as $host) {
            if (!isset($this->namespace[0])) {
                $success = $host->flushdb() && $success;
                continue;
            }

            foreach (new Keyspace($host, $this->namespace . '*', 1000) as $keys) {
                $host->del($keys);
            }
        }

        return $success;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function deleteItems(array $keys): bool
    {
        if ($keys !== []) {
            return $this->client->del($keys) === \count($keys);
        }

        return true;
    }

    /**
     * @inheritDoc
     *
     * @param array<string, mixed> $values
     *
     * @return bool|list<string>
     */
    #[Override]
    public function save(array $values, int $lifespan)
    {
        $serialized = [];
        $failed = [];
        foreach ($values as $id => $value) {
            try {
                $serialized[$id] = serialize($value);
            } catch (Exception) {
                $failed[] = $id;
            }
        }

        if ($serialized === []) {
            return $failed;
        }

        if (0 >= $lifespan) {
            $this->client->mset($serialized);

            return $failed;
        }

        $this->client->pipeline(
            /** @param Client $pipe */
            static function ($pipe) use ($serialized, $lifespan): void {
                foreach ($serialized as $id => $value) {
                    $pipe->setex($id, $lifespan, $value);
                }
            }
        );

        return $failed === [] ? true : $failed;
    }

    /**
     * @return list<Client>|null
     */
    protected function getHosts(Client $client): ?array
    {
        $connection = $client->getConnection();

        if ($connection instanceof PredisCluster) {
            $hosts = [];
            foreach ($connection as $c) {
                $hosts[] = new Client($c);
            }
        } elseif ($connection instanceof RedisCluster) {
            return null;
        } else {
            $hosts = [$this->client];
        }

        return $hosts;
    }
}
