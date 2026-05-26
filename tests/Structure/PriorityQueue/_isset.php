<?php
namespace Altair\Tests\Structure\PriorityQueue;

trait _isset
{
    public function testArrayAccessIsset(): void
    {
        $instance = static::getInstance();
        $this->expectArrayAccessUnsupportedException();
        isset($instance['?']);
    }
}
