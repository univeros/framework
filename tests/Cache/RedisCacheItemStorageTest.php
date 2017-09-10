<?php

namespace Altair\Tests\Cache;

use Altair\Cache\Storage\RedisCacheItemStorage;
use Redis;

class RedisCacheItemStorageTest extends AbstractStorageTestCase
{
    protected function setUp()
    {
        $redis = new Redis();
        $redis->connect('localhost', 6379);

        $this->store = new RedisCacheItemStorage($redis, 'test');
    }
}
