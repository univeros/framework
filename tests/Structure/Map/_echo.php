<?php
namespace Altair\Tests\Structure\Map;

trait _echo
{
    public function testEcho()
    {
        $this->assertInstanceToString($this->getInstance());
    }
}
