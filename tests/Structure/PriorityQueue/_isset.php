<?php
namespace Altair\tests\Structure\PriorityQueue;

trait _isset
{
    public function testArrayAccessIsset()
    {
        $instance = $this->getInstance();
        $this->expectArrayAccessUnsupportedException();
        isset($instance['?']);
    }
}
