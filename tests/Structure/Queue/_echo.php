<?php
namespace Altair\tests\Structure\Queue;

trait _echo
{
    public function testEcho()
    {
        $this->assertInstanceToString($this->getInstance());
    }
}
