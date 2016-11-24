<?php
namespace Altair\tests\Structure\Stack;

trait _empty
{
    public function testArrayAccessEmpty()
    {
        $set = $this->getInstance();
        $this->expectArrayAccessUnsupportedException();
        empty($set['a']);
    }
}
