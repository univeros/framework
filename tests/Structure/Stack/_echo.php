<?php
namespace Altair\tests\Structure\Stack;

trait _echo
{
    public function testEcho()
    {
        $this->assertInstanceToString($this->getInstance());
    }
}
