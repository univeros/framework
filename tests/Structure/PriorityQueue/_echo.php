<?php
namespace Altair\tests\Structure\PriorityQueue;

trait _echo
{
    public function testEcho()
    {
        $this->assertInstanceToString($this->getInstance());
    }
}
