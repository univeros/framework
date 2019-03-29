<?php declare(strict_types=1);

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

class MemcachedCacheItemStorage implements CacheItemStorageInterface
{
    protected $client;
    protected $maxIdLength = 250;

    /**
     * MemcachedCacheItemPoolStorage constructor.
     *
     * @param Memcached $memcached
     */
    public function __construct(Memcached $memcached)
    {
        if (!(extension_loaded('memcached') && version_compare(phpversion('memcached'), '2.2.0', '>='))) {
            throw new CacheException('Memcached >= 2.2.0 is required.');
        }
        $opt = $memcached->getOption(Memcached::OPT_SERIALIZER);
        if (Memcached::SERIALIZER_PHP !== $opt && Memcached::SERIALIZER_IGBINARY !== $opt) {
            throw new CacheException('MemcachedStorage: "serializer" option must be "php" or "igbinary".');
        }
        $this->maxIdLength -= strlen($memcached->getOption(Memcached::OPT_PREFIX_KEY));

        $this->client = $memcached;
    }

    /**
     * @inheritdoc
     */
    public function getMaxIdLength(): ?int
    {
        return $this->maxIdLength;
    }

    /**
     * @inheritdoc
     */
    public function getItems(array $keys = []): array
    {
        return $this->checkResponse($this->client->getMulti($keys));
    }

    /**
     * @inheritdoc
     */
    public function hasItem(string $key): bool
    {
        return false !== $this->client->get($key) ||
            $this->checkResponse(Memcached::RES_SUCCESS === $this->client->getResultCode());
    }

    /**
     * @inheritdoc
     */
    public function clear(): bool
    {
        return $this->checkResponse($this->client->flush());
    }

    /**
     * @inheritdoc
     */
    public function deleteItems(array $keys): bool
    {
        $success = true;

        foreach ($this->checkResponse((array)$this->client->deleteMulti($keys)) as $result) {
            if (Memcached::RES_SUCCESS !== $result && Memcached::RES_NOTFOUND !== $result) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * @inheritdoc
     */
    public function save(array $values, int $lifespan)
    {
        return $this->checkResponse($this->client->setMulti($values, $lifespan));
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
    protected function checkResponse($result)
    {
        $code = $this->client->getResultCode();

        if (Memcached::RES_SUCCESS === $code || Memcached::RES_NOTFOUND === $code) {
            return $result;
        }

        throw new CacheException(
            sprintf(
                'MemcachedStorage client error: %s.',
                strtolower($this->client->getResultMessage())
            )
        );
    }
}
