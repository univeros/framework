<?php
namespace Altair\tests\Structure\Set;

trait _unset
{
    public function testArrayAccessUnset()
    {
        $set = $this->getInstance();
        $this->expectArrayAccessUnsupportedException();
        unset($set['a']);
    }
}
