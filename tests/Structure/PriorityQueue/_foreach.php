<?php
namespace Altair\Tests\Structure\PriorityQueue;

trait _foreach
{
    public function testForEach(): void
    {
        $instance = static::getInstance();

        $instance->push('a', 1);
        $instance->push('c', 3);
        $instance->push('b', 2);

        $data = [];

        foreach ($instance as $value) {
            $data[] = $value;
        }

        $this->assertEquals(['c', 'b', 'a'], $data);

        // Test that foreach is destructive.
        $this->assertTrue($instance->isEmpty());
        $this->assertCount(0, $instance);
        $this->assertToArray([], $instance);

    }
}
