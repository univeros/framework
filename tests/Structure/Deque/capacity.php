<?php

namespace Altair\Tests\Structure\Deque;

use Altair\Structure\Contracts\CapacityInterface;

trait capacity
{
    public function testCapacity(): void
    {
        $min = CapacityInterface::MIN_CAPACITY;

        $instance = static::getInstance();
        $this->assertEquals($min, $instance->capacity());

        for ($i = 0; $i < $min; $i++) {
            $instance->push($i);
        }

        // Should resize when full after push
        $this->assertEquals($min * 2, $instance->capacity());
    }

    public function testAutoTruncate(): void
    {
        $min = CapacityInterface::MIN_CAPACITY;
        $num = $min * 16;

        $instance = static::getInstance(range(1, $num - 1));
        $expected = $num / 2;

        for ($i = 0; $i <= 3 * $num / 4; $i++) {
            $instance->pop();
        }

        $this->assertEquals($expected, $instance->capacity());
    }

    public function testClearResetsCapacity(): void
    {
        $min = CapacityInterface::MIN_CAPACITY;

        $instance = static::getInstance(range(1, self::MANY));
        $instance = $instance->clear();
        $this->assertEquals($min, $instance->capacity());
    }
}
