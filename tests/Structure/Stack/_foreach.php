<?php
namespace Altair\Tests\Structure\Stack;

trait _foreach
{
    public function testForEach()
    {
        $instance = $this->getInstance();

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

        // Just to make sure that an iteration attempt can still be made.
        foreach ($instance as $value) {
        }
        foreach ($instance as $value) {
        }
    }
}
