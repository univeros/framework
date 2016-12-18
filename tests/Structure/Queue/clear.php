<?php
namespace Altair\Tests\Structure\Queue;

trait clear
{
    public function testClear()
    {
        $instance = $this->getInstance($this->sample());
        $instance = $instance->clear();

        $this->assertToArray([], $instance);
        $this->assertCount(0, $instance);
    }
}
