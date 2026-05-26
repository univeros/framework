<?php
namespace Altair\Tests\Structure\PriorityQueue;

trait _isset
{
    public function testArrayAccessIsset()
    {
        $instance = static::getInstance();
        $this->expectArrayAccessUnsupportedException();
        isset($instance['?']);
    }
}
