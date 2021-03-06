<?php declare(strict_types=1);

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
use Exception;
use Redis;

class RedisCacheItemStorage implements CacheItemStorageInterface
{
    use RedisNamespaceValidationAwareTrait;

    protected $client;
    protected $namespace;

    /**
     * RedisCacheItemPoolStorage constructor.
     *
     * @param Redis $redis
     * @param string $namespace
     */
    public function __construct(Redis $redis, $namespace = '')
    {
        $info = $redis->info('Server');
        $info = $info['Server'] ?? $info;
        if (!version_compare($info['redis_version'], '2.8', '>=')) {
            throw new InvalidArgumentException(sprintf('%s requires Redis 2.8 or above.', static::class));
        }
        $this->client = $redis;
        $this->useNamespace($namespace);
    }

    /**
     * @inheritDoc
     */
    public function getMaxIdLength(): ?int
    {
        return null;
    }

    /**
     * @inheritDoc
     * @throws \ErrorException
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
     * @inheritDoc
     */
    public function hasItem(string $key): bool
    {
        return (bool)$this->client->exists($key);
    }

    /**
     * @inheritDoc
     */
    public function clear(): bool
    {
        if (!isset($this->namespace[0])) {
            return $this->client->flushDB();
        }

        $cursor = null;

        do {
            $keys = $this->client->scan($cursor, $this->namespace . '*', 1000);
            if (isset($keys[1]) && is_array($keys[1])) {
                [$cursor, $keys] = $keys;
            }
            if ($keys) {
                $this->client->del($keys);
            }
        } while ($cursor = (int)$cursor);

        return true;
    }

    /**
     * @inheritDoc
     */
    public function deleteItems(array $keys): bool
    {
        if (!empty($keys)) {
            return $this->client->del($keys) === count($keys);
        }

        return true;
    }

    /**
     * @inheritDoc
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
        $this->client->multi(Redis::PIPELINE);
        $ids = [];
        foreach ($serialized as $id => $value) {
            if (0 >= $lifespan) {
                $this->client->set($id, $value);
            } else {
                $this->client->setex($id, $lifespan, $value);
            }
            $ids[] = $id;
        }
        $results = $this->client->exec();

        foreach ($ids as $key => $id) {
            $result = $results[$key];
            if (true !== $result) {
                $failed[] = $id;
            }
        }

        return empty($failed) ? true : $failed;
    }

    /**
     *
     * @param string $namespace
     * @throws InvalidArgumentException if the namespace contains invalid characters
     */
    public function validateNamespace(string $namespace): void
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
