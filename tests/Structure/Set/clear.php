<?php
namespace Altair\Tests\Structure\Set;

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
