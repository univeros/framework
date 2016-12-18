<?php
namespace Altair\Tests\Structure\Queue;

trait _isset
{
    public function testArrayAccessIsset()
    {
        $set = $this->getInstance();
        $this->expectArrayAccessUnsupportedException();
        isset($set['a']);
    }
}
