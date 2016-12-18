<?php
namespace Altair\Tests\Structure\Queue;

trait _unset
{
    public function testArrayAccessUnset()
    {
        $set = $this->getInstance();
        $this->expectArrayAccessUnsupportedException();
        unset($set['a']);
    }
}
