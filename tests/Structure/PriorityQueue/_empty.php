<?php
namespace Altair\tests\Structure\PriorityQueue;

trait _empty
{
    public function testArrayAccessEmpty()
    {
        $instance = $this->getInstance();
        $this->expectArrayAccessUnsupportedException();
        empty($instance['?']);
    }
}
