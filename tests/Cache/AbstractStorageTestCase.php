<?php

namespace Altair\Tests\Cache;

use Altair\Cache\Contracts\CacheItemStorageInterface;
use PHPUnit\Framework\TestCase;

abstract class AbstractStorageTestCase extends TestCase
{
    /**
     * @var CacheItemStorageInterface
     */
    protected $store;

    protected function tearDown()
    {
        $this->store->clear();
    }

    public function testStore()
    {
        $this->assertFalse($this->store->hasItem($key = 'somekey'));
        $this->assertEmpty($this->store->getItems([$key]));
        $this->store->save([$key => ($value = 'somevalue')], 0);
        $this->assertTrue($this->store->hasItem($key));
        $items = $this->store->getItems([$key]);
        $this->assertEquals($value, current($items));
        $this->store->deleteItems([$key]);
        $this->assertFalse($this->store->hasItem($key));
    }
}
