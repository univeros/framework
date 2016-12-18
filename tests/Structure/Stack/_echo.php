<?php
namespace Altair\Tests\Structure\Stack;

trait _echo
{
    public function testEcho()
    {
        $this->assertInstanceToString($this->getInstance());
    }
}
