<?php
namespace Altair\Tests\Structure\PriorityQueue;

trait _echo
{
    public function testEcho(): void
    {
        $this->assertInstanceToString(static::getInstance());
    }
}
