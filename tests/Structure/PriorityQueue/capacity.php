<?php
namespace Altair\tests\Structure\PriorityQueue;

use Altair\Structure\Contracts\CapacityInterface;

trait capacity
{
    public function testCapacity()
    {
        $min = CapacityInterface::MIN_CAPACITY;

        $instance = $this->getInstance();
        $this->assertEquals($min, $instance->capacity());

        for ($i = 0; $i < $min; $i++) {
            $instance->push($i, 0);
        }

        // Should not resize when full after push
        $this->assertEquals($min, $instance->capacity());

        // Should resize if full before push
        $instance->push($min, 0);
        $this->assertEquals($min * 2, $instance->capacity());
    }

    public function testAutoTruncate()
    {
        $instance = $this->getInstance(range(1, self::MANY));
        $expected = $instance->capacity() / 2;

        for ($i = 0; $i <= 3 * self::MANY / 4; $i++) {
            $instance->pop();
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
