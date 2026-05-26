<?php
namespace Altair\Tests\Structure\PriorityQueue;

trait count
{
    public function testCount(): void
    {
        $instance = static::getInstance();

        foreach (range(1, self::MANY) as $i) {
            $instance->push($i, random_int(0, mt_getrandmax()));
        }

        $this->assertCount(self::MANY, $instance);
    }
}
