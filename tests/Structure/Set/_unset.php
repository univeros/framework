<?php
namespace Altair\Tests\Structure\Set;

trait _unset
{
    public function testArrayAccessUnset()
    {
        $set = static::getInstance();
        $this->expectArrayAccessUnsupportedException();
        unset($set['a']);
    }
}
