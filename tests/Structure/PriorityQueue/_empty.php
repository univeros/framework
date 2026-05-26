<?php
namespace Altair\Tests\Structure\PriorityQueue;

trait _empty
{
    public function testArrayAccessEmpty(): void
    {
        $instance = static::getInstance();
        $this->expectArrayAccessUnsupportedException();
        empty($instance['?']);
    }
}
