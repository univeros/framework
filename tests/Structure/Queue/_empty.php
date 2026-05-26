<?php
namespace Altair\Tests\Structure\Queue;

trait _empty
{
    public function testArrayAccessEmpty()
    {
        $set = static::getInstance();
        $this->expectArrayAccessUnsupportedException();
        empty($set['a']);
    }
}
