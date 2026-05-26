<?php
namespace Altair\Tests\Structure\Stack;

trait clear
{
    public function testClear()
    {
        $instance = static::getInstance(static::sample());
        $instance = $instance->clear();

        $this->assertToArray([], $instance);
        $this->assertCount(0, $instance);
    }
}
