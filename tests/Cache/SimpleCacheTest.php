<?php

namespace Altair\Tests\Cache;

use Altair\Cache\CacheItemPool;
use Altair\Cache\SimpleCache;
use Altair\Cache\Storage\FilesystemCacheItemStorage;
use Altair\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;

class SimpleCacheTest extends TestCase
{
    /**
     * @var Filesystem
     */
    private $fs;
    /**
     * @var CacheItemPool
     */
    private $pool;
    /**
     * @var SimpleCache
     */
    private $cache;

    protected function setUp()
    {
        $this->fs = new Filesystem();
        $this->fs->makeDirectory(__DIR__ . '/tmp');
        $this->pool = new CacheItemPool(new FilesystemCacheItemStorage($this->fs, __DIR__ . '/tmp'));
        $this->cache = new SimpleCache($this->pool);
    }

    protected function tearDown()
    {
        $this->cache->clear();
        $this->fs->deleteDirectory(__DIR__ . '/tmp');
    }

    public function testCache()
    {
        $this->assertFalse($this->cache->has($key = 'somekey'));
        $this->assertNull($this->cache->get($key));
        $this->cache->set($key, $value = 'somevalue');
        $this->assertTrue($this->cache->has($key));
        $this->assertEquals($value, $this->cache->get($key));
        $this->cache->delete($key);
        $this->assertFalse($this->cache->has($key));
    }
    public function testCacheWithExpiry()
    {
        $this->cache->set($key = 'somekey', $value = 'somevalue');
        $this->assertEquals($value, $this->cache->get($key));
        $this->cache->delete($key);
        $this->cache->set($key, $value, 3600);
        $this->assertEquals($value, $this->cache->get($key));
        $this->cache->delete($key);
        $this->cache->set($key, $value, 1);
        sleep(2);
        $this->assertNull($this->cache->get($key));
    }
    public function testCacheMultiple()
    {
        $this->cache->set($key1 = 'somekey', $value1 = 'somevalue');
        $this->cache->set($key2 = 'otherkey', $value2 = 'othervalue');
        $values = $this->cache->getMultiple([$key1, $key2]);
        $this->assertEquals($value1, $values[$key1]);
        $this->assertEquals($value2, $values[$key2]);
        $this->cache->deleteMultiple([$key1, $key2]);
        $this->assertFalse($this->cache->has($key1));
        $this->assertFalse($this->cache->has($key2));
        $this->cache->setMultiple([
            $key1 => $value1,
            $key2 => $value2,
        ]);
        $this->assertTrue($this->cache->has($key1));
        $this->assertTrue($this->cache->has($key2));
        $this->assertEquals($value1, $this->cache->get($key1));
        $this->assertEquals($value2, $this->cache->get($key2));
    }
}
