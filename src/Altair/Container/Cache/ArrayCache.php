<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Container\Cache;

use Altair\Container\Contracts\ReflectionCacheInterface;

class ArrayCache implements ReflectionCacheInterface
{
    /**
     * @var array
     */
    protected $cache = [];

    /**
     * @param string $key
     *
     * @return bool|mixed
     */
    public function get(string $key)
    {
        // some maybe have null values and still valid (ie no constructor)
        return isset($this->cache[$key]) || array_key_exists($key, $this->cache) ? $this->cache[$key] : false;
    }

    /**
     * @inheritDoc
     */
    public function put(string $key, $data): ReflectionCacheInterface
    {
        $this->cache[$key] = $data;

        return $this;
    }
}
