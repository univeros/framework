<?php
namespace Altair\Tests\Structure\PriorityQueue;

trait isEmpty
{
    public function testIsEmpty(): void
    {
        $instance = static::getInstance();
        $this->assertTrue($instance->isEmpty());

        $instance->push('a', 1);
        $this->assertFalse($instance->isEmpty());

        $instance->pop();
        $this->assertTrue($instance->isEmpty());
    }
}
