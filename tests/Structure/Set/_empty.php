<?php
namespace Altair\Tests\Structure\Set;

trait _empty
{
    public function testArrayAccessEmpty()
    {
        $set = $this->getInstance();
        $this->expectArrayAccessUnsupportedException();
        empty($set['a']);
    }
}
