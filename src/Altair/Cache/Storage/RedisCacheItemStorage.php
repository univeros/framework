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
use Redis;

class RedisCacheItemStorage implements CacheItemStorageInterface
{
    use RedisNamespaceValidationAwareTrait;

    protected Redis $client;

    protected string $namespace = '';

    /**
     * RedisCacheItemPoolStorage constructor.
     */
    public function __construct(Redis $redis, string $namespace = '')
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
        if (!isset($this->namespace[0])) {
            return $this->client->flushDB();
        }

        $cursor = null;

        do {
            $keys = $this->client->scan($cursor, $this->namespace . '*', 1000);
            if (isset($keys[1]) && \is_array($keys[1])) {
                [$cursor, $keys] = $keys;
            }

            if ($keys) {
                $this->client->del($keys);
            }
        } while ($cursor = (int) $cursor);

        return true;
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

        $this->client->multi(Redis::PIPELINE);
        $ids = [];
        foreach ($serialized as $id => $value) {
            $this->client->setex($id, $lifespan, $value);
            $ids[] = $id;
        }

        $results = $this->client->exec();

        foreach ($ids as $key => $id) {
            $result = $results[$key];
            if (true !== $result) {
                $failed[] = $id;
            }
        }

        return $failed === [] ? true : $failed;
    }

    /**
     *
     * @throws InvalidArgumentException if the namespace contains invalid characters
     */
    public function validateNamespace(string $namespace): void
    {
        if (preg_match('/[^-+_.A-Za-z0-9]/', $namespace, $match)) {
            throw new InvalidArgumentException(
                \sprintf(
                    'The namespace for %s contains "%s" but only chars in [-+_.A-Za-z0-9] are allowed.',
                    static::class,
                    $match[0]
                )
            );
        }

        $this->namespace = $namespace;
    }
}
