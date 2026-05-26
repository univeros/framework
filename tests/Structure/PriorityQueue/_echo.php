<?php
namespace Altair\Tests\Structure\PriorityQueue;

trait _echo
{
    public function testEcho()
    {
        $this->assertInstanceToString(static::getInstance());
    }
}
