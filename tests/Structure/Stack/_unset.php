<?php
namespace Altair\tests\Structure\Stack;

trait _unset
{
    public function testArrayAccessUnset()
    {
        $set = $this->getInstance();
        $this->expectArrayAccessUnsupportedException();
        unset($set['a']);
    }
}
