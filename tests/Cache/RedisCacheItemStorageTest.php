<?php

declare(strict_types=1);

namespace Altair\Tests\Cache;

use Altair\Cache\Storage\RedisCacheItemStorage;
use Redis;

class RedisCacheItemStorageTest extends AbstractStorageTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('ext-redis is not loaded.');
        }

        $redis = new Redis();
        $redis->connect('localhost', 6379);

        $this->store = new RedisCacheItemStorage($redis, 'test');
    }
}
