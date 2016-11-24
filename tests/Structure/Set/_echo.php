<?php
namespace Altair\tests\Structure\Set;

trait _echo
{
    public function testEcho()
    {
        $this->assertInstanceToString($this->getInstance());
    }
}
