<?php
namespace Altair\Tests\Structure\PriorityQueue;

trait _unset
{
    public function testArrayAccessUnset()
    {
        $instance = static::getInstance();
        $this->expectArrayAccessUnsupportedException();
        unset($instance['?']);
    }
}
