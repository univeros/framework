<?php
namespace Altair\Tests\Structure\Stack;

trait _unset
{
    public function testArrayAccessUnset(): void
    {
        $set = static::getInstance();
        $this->expectArrayAccessUnsupportedException();
        unset($set['a']);
    }
}
