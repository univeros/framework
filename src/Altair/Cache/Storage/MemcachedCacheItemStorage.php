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
use Altair\Cache\Exception\CacheException;
use Memcached;
use Override;

class MemcachedCacheItemStorage implements CacheItemStorageInterface
{
    protected Memcached $client;

    protected int $maxIdLength = 250;

    /**
     * MemcachedCacheItemPoolStorage constructor.
     */
    public function __construct(Memcached $memcached)
    {
        if (!(\extension_loaded('memcached') && version_compare(phpversion('memcached'), '2.2.0', '>='))) {
            throw new CacheException('Memcached >= 2.2.0 is required.');
        }

        $opt = $memcached->getOption(Memcached::OPT_SERIALIZER);
        if (Memcached::SERIALIZER_PHP !== $opt && Memcached::SERIALIZER_IGBINARY !== $opt) {
            throw new CacheException('MemcachedStorage: "serializer" option must be "php" or "igbinary".');
        }

        $this->maxIdLength -= \strlen((string) $memcached->getOption(Memcached::OPT_PREFIX_KEY));

        $this->client = $memcached;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getMaxIdLength(): ?int
    {
        return $this->maxIdLength;
    }

    /**
     * @inheritDoc
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function getItems(array $keys = []): array
    {
        return $this->checkResponse($this->client->getMulti($keys));
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function hasItem(string $key): bool
    {
        if (false !== $this->client->get($key)) {
            return true;
        }

        return Memcached::RES_SUCCESS === $this->client->getResultCode();
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function clear(): bool
    {
        return $this->checkResponse($this->client->flush());
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function deleteItems(array $keys): bool
    {
        $success = true;

        foreach ($this->checkResponse($this->client->deleteMulti($keys)) as $result) {
            if (Memcached::RES_SUCCESS !== $result && Memcached::RES_NOTFOUND !== $result) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * @inheritDoc
     *
     * @param array<string, mixed> $values
     */
    #[Override]
    public function save(array $values, int $lifespan): bool
    {
        return Memcached::RES_SUCCESS === $this->checkResponse($this->client->setMulti($values, $lifespan));
    }

    /**
     * Checks the response of a call to the Memcached client and throws an error if it wasn't an expected result code.
     *
     * @param mixed $result The returned value by a Memcached client call.
     *
     * @throws CacheException if the result code is an unexpected value.
     *
     * @return mixed
     */
    protected function checkResponse(mixed $result)
    {
        $code = $this->client->getResultCode();

        if (Memcached::RES_SUCCESS === $code || Memcached::RES_NOTFOUND === $code) {
            return $result;
        }

        throw new CacheException(
            \sprintf(
                'MemcachedStorage client error: %s.',
                strtolower($this->client->getResultMessage())
            )
        );
    }
}
