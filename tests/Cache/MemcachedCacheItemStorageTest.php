<?php

namespace Altair\Tests\Cache;

use Altair\Cache\Storage\MemcachedCacheItemStorage;
use Memcached;

class MemcachedCacheItemStorageTest extends AbstractStorageTestCase
{
    protected function setUp()
    {
        $memcached = new Memcached();
        $memcached->addServer('localhost', 11211);

        $this->store = new MemcachedCacheItemStorage($memcached);
    }
}
