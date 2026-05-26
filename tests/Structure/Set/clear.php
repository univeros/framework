<?php
namespace Altair\Tests\Structure\Set;

trait clear
{
    public function testClear(): void
    {
        $instance = static::getInstance(static::sample());
        $instance = $instance->clear();

        $this->assertToArray([], $instance);
        $this->assertCount(0, $instance);
    }
}
