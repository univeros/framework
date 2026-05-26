<?php

namespace Altair\Tests\Cache;

use Altair\Cache\CacheItem;
use Closure;
use PHPUnit\Framework\TestCase;

class CacheItemTest extends TestCase
{
    private ?\Closure $fn;

    #[\Override]
    protected function setUp(): void    {
        $this->fn = Closure::bind(
            function (string $key, $value, bool $isHit): CacheItem {
                $cacheItem = new CacheItem();
                $cacheItem->{'key'} = $key;
                $cacheItem->{'value'} = $value;
                $cacheItem->{'isHit'} = $isHit;

                return $cacheItem;
            },
            null,
            CacheItem::class
        );
    }

    public function testKey(): void
    {
        /** @var CacheItem $item */
        $item = call_user_func($this->fn, $key = 'somekey', null, false);
        $item->set($key = 'somekey');
        $this->assertEquals($key, $item->getKey());
    }

    public function testExpiresAfterInterval(): void
    {
        /** @var CacheItem $item */
        $item = call_user_func($this->fn, 'somekey', null, false);

        $this->assertFalse($item->isHit());
        $di = new \DateInterval('P30M');
        $item->expiresAfter($di);
        $reflection = new \ReflectionObject($item);
        $property = $reflection->getProperty('expirationTime');
        $property->setAccessible(true);

        $value = $property->getValue($item);
        $this->assertNotNull($value);
        $this->assertGreaterThan(time(), $value);
    }

    public function testExpiresAfterSeconds(): void
    {
        /** @var CacheItem $item */
        $item = call_user_func($this->fn, 'somekey', null, false);
        $reflection = new \ReflectionObject($item);
        $property = $reflection->getProperty('expirationTime');
        $property->setAccessible(true);
        $this->assertNull($property->getValue($item));
        $item->expiresAfter(3600);
        $this->assertNotNull($property->getValue($item));
        $this->assertGreaterThan(time(), $property->getValue($item));
    }

    public function testExpiresAfterNull(): void
    {
        /** @var CacheItem $item */
        $item = call_user_func($this->fn, 'somekey', null, false);
        $reflection = new \ReflectionObject($item);
        $property = $reflection->getProperty('expirationTime');
        $property->setAccessible(true);
        $this->assertNull($property->getValue($item));
        $item->expiresAfter(null);
        $this->assertNull($property->getValue($item));
    }

    public function testExpiresAt(): void
    {
        /** @var CacheItem $item */
        $item = call_user_func($this->fn, 'somekey', null, false);
        $reflection = new \ReflectionObject($item);
        $property = $reflection->getProperty('expirationTime');
        $property->setAccessible(true);
        $this->assertNull($property->getValue($item));
        $item->expiresAt($dt = date_create());
        $this->assertNotNull($property->getValue($item));
        $this->assertEquals($dt->format('U'), $property->getValue($item));
    }

    public function testExpiresAtNull(): void
    {
        /** @var CacheItem $item */
        $item = call_user_func($this->fn, 'somekey', null, false);
        $reflection = new \ReflectionObject($item);
        $property = $reflection->getProperty('expirationTime');
        $property->setAccessible(true);
        $this->assertNull($property->getValue($item));
        $item->expiresAt(null);
        $this->assertNull($property->getValue($item));
    }

    public function testHit(): void
    {
        /** @var CacheItem $item */
        $item = call_user_func($this->fn, 'somekey', null, false);
        $this->assertFalse($item->isHit());
        /** @var CacheItem $item */
        $item = call_user_func($this->fn, 'somekey', null, true);
        $this->assertTrue($item->isHit());
    }

    public function testSet(): void
    {
        /** @var CacheItem $item */
        $item = call_user_func($this->fn, 'somekey', null, false);
        $this->assertNull($item->get());
        $item->set($value = 'somevalue');
        $this->assertEquals($value, $item->get());
    }

    public function testValue(): void
    {
        /** @var CacheItem $item */
        $item = call_user_func($this->fn, 'somekey', null, false);
        $item->set($value = 'somevalue');
        $this->assertEquals($value, $item->get());
    }
}
