<?php
namespace Altair\tests\Structure\Map;

trait _echo
{
    public function testEcho()
    {
        $this->assertInstanceToString($this->getInstance());
    }
}
