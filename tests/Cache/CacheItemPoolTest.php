<?php

namespace Altair\Tests\Cache;

use Altair\Cache\CacheItemPool;
use Altair\Cache\Storage\FilesystemCacheItemStorage;
use Altair\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;

class CacheItemPoolTest extends TestCase
{
    /**
     * @var Filesystem
     */
    private $fs;
    /**
     * @var CacheItemPool
     */
    private $pool;

    protected function setUp()
    {
        $this->fs = new Filesystem();
        $this->fs->makeDirectory(__DIR__ . '/tmp');
        $this->pool = new CacheItemPool(new FilesystemCacheItemStorage($this->fs, __DIR__ . '/tmp'));
    }

    protected function tearDown()
    {
        $this->pool->clear();
        $this->fs->deleteDirectory(__DIR__ . '/tmp');
    }

    public function testPool()
    {
        $this->assertFalse($this->pool->hasItem($key = 'somekey'));
        $item = $this->pool->getItem($key);
        $item->set($value = 'something');
        $this->pool->save($item);
        $this->assertTrue($this->pool->hasItem($key));
        $item = $this->pool->getItem($key);
        $this->assertEquals($value, $item->get());
        $this->assertTrue($item->isHit());
        $this->pool->deleteItem($key);
        $this->assertFalse($this->pool->hasItem($key));
    }

    public function testPoolWithExpireTime()
    {
        $item = $this->pool->getItem($key = 'somekey');
        $item->set($value = 'something');
        $this->pool->save($item);
        $item = $this->pool->getItem($key);
        $this->assertEquals($value, $item->get());
        $this->pool->deleteItem($key);
        $item = $this->pool->getItem($key);
        $item->set($value = 'something');
        $item->expiresAfter(new \DateInterval('P30M'));
        $this->pool->save($item);
        $item = $this->pool->getItem($key);
        $this->assertEquals($value, $item->get());
        $item = $this->pool->getItem($key);
        $item->set($value = 'something');
        $item->expiresAfter(3600);
        $this->pool->save($item);
        $item = $this->pool->getItem($key);
        $this->assertEquals($value, $item->get());
        $item = $this->pool->getItem($key);
        $item->set($value = 'something');
        $item->expiresAfter(1);
        sleep(2);
        $this->pool->save($item);
        $item = $this->pool->getItem($key);
        $this->assertNull($item->get());
    }

    public function testPoolMultiple()
    {
        $item = $this->pool->getItem($key1 = 'somekey');
        $item->set($value1 = 'somevalue');
        $this->pool->save($item);
        $item = $this->pool->getItem($key2 = 'otherkey');
        $item->set($value2 = 'othervalue');
        $this->pool->save($item);
        $values = $this->pool->getItems([$key1, $key2]);
        foreach ($values as $key => $item) {
            if ($key1 === $key) {
                $this->assertEquals($value1, $item->get());
            }
            if ($key2 === $key) {
                $this->assertEquals($value2, $item->get());
            }
        }
        $this->pool->deleteItems([$key1, $key2]);
        $this->assertFalse($this->pool->hasItem($key1));
        $this->assertFalse($this->pool->hasItem($key2));
    }

    public function testSaveDeferred()
    {
        $this->assertFalse($this->pool->hasItem($key = 'somekey'));
        $item = $this->pool->getItem($key);
        $item->set($value = 'something');
        $this->pool->saveDeferred($item);
        $this->assertTrue($this->pool->hasItem($key));
        $this->pool->commit();
        $this->assertTrue($this->pool->hasItem($key));
    }
}
