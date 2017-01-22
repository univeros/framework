<?php
namespace Altair\Cache\Adapter;

use Altair\Cache\Contracts\CacheItemPoolAdapterInterface;
use Altair\Cache\Exception\InvalidArgumentException;
use Altair\Cache\Support\CacheItemUnserializer;
use Exception;
use Predis\Client;
use Predis\Collection\Iterator\Keyspace;
use Predis\Connection\Aggregate\PredisCluster;
use Predis\Connection\Aggregate\RedisCluster;

class RedisCacheItemPoolAdapter implements CacheItemPoolAdapterInterface
{
    protected $client;
    protected $namespace;

    /**
     * RedisCacheItemPoolAdapter constructor.
     *
     * @param Client $redis
     */
    public function __construct(Client $redis)
    {
        $info = $redis->info('Server');
        $info = $info['Server']?? $info;
        if (!version_compare($info['redis_version'], '2.8', '>=')) {
            throw new InvalidArgumentException(sprintf('%s requires Redis 2.8 or above.', static::class));
        }
        $this->client = $redis;
    }

    /**
     * @inheritdoc
     */
    public function getMaxIdLength(): ?int
    {
        return null;
    }

    /**
     * @inheritdoc
     */
    public function getItems(array $keys = []): array
    {
        $items = [];
        if (!empty($keys)) {
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
     * @inheritdoc
     */
    public function hasItem(string $key): bool
    {
        return (bool)$this->client->exists($key);
    }

    /**
     * @inheritdoc
     */
    public function clear(): bool
    {
        // When using a native Redis cluster, clearing the cache cannot work and always returns false.
        // Clearing the cache should then be done by any other means (e.g. by restarting the cluster).
        // - Thanks Symfony ;)
        $success = true;
        $connection = $this->client->getConnection();
        if ($connection instanceof PredisCluster) {
            $hosts = [];
            foreach ($connection as $c) {
                $hosts[] = new Client($c);
            }
        } elseif ($connection instanceof RedisCluster) {
            return false;
        } else {
            $hosts = [$this->client];
        }

        /** @var Client $host */
        foreach ($hosts as $host) {
            if (!isset($this->namespace[0])) {
                $success = $host->flushdb() && $success;
                continue;
            }

            foreach ((new Keyspace($host, $this->namespace . '*', 1000)) as $keys) {
                $host->del($keys);
            }
        }

        return $success;
    }

    /**
     * @inheritdoc
     */
    public function deleteItems(array $keys): bool
    {
        if (!empty($keys)) {
            return $this->client->del($keys) === count($keys);
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function save(array $values, int $lifespan)
    {
        $serialized = $failed = [];
        foreach ($values as $id => $value) {
            try {
                $serialized[$id] = serialize($value);
            } catch (Exception $e) {
                $failed[] = $id;
            }
        }
        if (empty($serialized)) {
            return $failed;
        }
        if (0 >= $lifespan) {
            $this->client->mset($serialized);

            return $failed;
        }

        $this->client->pipeline(
            function ($pipe) use ($serialized, $lifespan) {
                /** @var Client $pipe */
                foreach ($serialized as $id => $value) {
                    $pipe->setex($id, $lifespan, $value);
                }
            }
        );

        return empty($failed) ? true : $failed;
    }

    /**
     * @throws InvalidArgumentException if the namespace contains invalid characters
     *
     * @param string $namespace
     */
    public function validateNamespace(string $namespace)
    {
        if (preg_match('/[^-+_.A-Za-z0-9]/', $namespace, $match)) {
            throw new InvalidArgumentException(
                sprintf(
                    'The namespace for %s contains "%s" but only chars in [-+_.A-Za-z0-9] are allowed.',
                    static::class,
                    $match[0]
                )
            );
        }
        $this->namespace = $namespace;
    }
}
