<?php
namespace Altair\Tests\Structure\Map;

use Altair\Structure\Contracts\CapacityInterface;

trait capacity
{
    public function testCapacity()
    {
        $min = CapacityInterface::MIN_CAPACITY;

        $instance = $this->getInstance();
        $this->assertEquals($min, $instance->capacity());

        for ($i = 0; $i < $min; $i++) {
            $instance[$i] = null;
        }

        // Should not resize when full after add
        $this->assertEquals($min, $instance->capacity());

        // Should resize when full before add
        $instance[$min] = null;
        $this->assertEquals($min * 2, $instance->capacity());
    }

    public function testAutoTruncate()
    {
        $instance = $this->getInstance(range(1, self::MANY));
        $expected = $instance->capacity() / 2;

        for ($i = 0; $i <= 3 * self::MANY / 4; $i++) {
            $instance->remove($i);
        }

        $this->assertEquals($expected, $instance->capacity());
    }

    public function testClearResetsCapacity()
    {
        $instance = $this->getInstance(range(1, self::MANY));
        $instance = $instance->clear();
        $this->assertEquals(CapacityInterface::MIN_CAPACITY, $instance->capacity());
    }
}
