<?php
namespace Altair\Tests\Structure\PriorityQueue;

trait _empty
{
    public function testArrayAccessEmpty()
    {
        $instance = static::getInstance();
        $this->expectArrayAccessUnsupportedException();
        empty($instance['?']);
    }
}
