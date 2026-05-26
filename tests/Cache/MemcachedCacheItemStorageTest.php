<?php

namespace Altair\Tests\Cache;

use Altair\Cache\Storage\MemcachedCacheItemStorage;
use Memcached;

class MemcachedCacheItemStorageTest extends AbstractStorageTestCase
{
    protected function setUp(): void
    {
        if (!extension_loaded('memcached')) {
            $this->markTestSkipped('ext-memcached is not loaded.');
        }

        $memcached = new Memcached();
        $memcached->addServer('localhost', 11211);

        $this->store = new MemcachedCacheItemStorage($memcached);
    }
}
