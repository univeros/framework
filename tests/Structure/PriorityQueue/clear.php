<?php
namespace Altair\Tests\Structure\PriorityQueue;

trait clear
{
    public function testClear(): void
    {
        $instance = static::getInstance();

        foreach (range(1, self::MANY) as $i) {
            $instance->push($i, random_int(0, mt_getrandmax()));
        }

        $this->assertCount(self::MANY, $instance);

        $instance = $instance->clear();
        $this->assertCount(0, $instance);
        $this->assertTrue($instance->isEmpty());
        $this->assertToArray([], $instance);
    }
}
