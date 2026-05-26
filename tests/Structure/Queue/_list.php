<?php
namespace Altair\Tests\Structure\Queue;

trait _list
{
    public function testList()
    {
        $instance = static::getInstance(['a', 'b', 'c']);
        $this->expectListNotSupportedException();
        list($a, $b, $c) = $instance;
    }
}
