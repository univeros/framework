<?php
namespace Altair\Tests\Structure\Stack;

trait _foreach
{
    public function testForEach(): void
    {
        $instance = static::getInstance();

        $instance->push('a');
        $instance->push('b');
        $instance->push('c');

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
