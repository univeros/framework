<?php
namespace Altair\Tests\Structure\PriorityQueue;

trait _unset
{
    public function testArrayAccessUnset()
    {
        $instance = $this->getInstance();
        $this->expectArrayAccessUnsupportedException();
        unset($instance['?']);
    }
}
