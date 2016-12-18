<?php
namespace Altair\Container\Cache;


use Altair\Container\Contracts\ReflectionCacheInterface;

class ArrayCache implements ReflectionCacheInterface
{
    protected $cache = [];

    public function get(string $key)
    {
        // some maybe have null values and still valid (ie no constructor)
        return isset($this->cache[$key]) || array_key_exists($key, $this->cache) ? $this->cache[$key] : false;
    }

    public function put(string $key, $data)
    {
        $this->cache[$key] = $data;
    }

}
