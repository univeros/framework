<?php
namespace Altair\Tests\Structure\Queue;

trait _echo
{
    public function testEcho()
    {
        $this->assertInstanceToString($this->getInstance());
    }
}
