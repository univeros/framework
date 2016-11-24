<?php
namespace Altair\tests\Structure\Set;

trait _isset
{
    public function testArrayAccessIsset()
    {
        $set = $this->getInstance();
        $this->expectArrayAccessUnsupportedException();
        isset($set['a']);
    }
}
