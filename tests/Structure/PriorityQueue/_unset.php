<?php
namespace Altair\Tests\Structure\PriorityQueue;

trait _unset
{
    public function testArrayAccessUnset(): void
    {
        $instance = static::getInstance();
        $this->expectArrayAccessUnsupportedException();
        unset($instance['?']);
    }
}
