<?php
namespace Altair\tests\Structure\Sequence;

trait _echo
{
    public function testEcho()
    {
        $this->assertInstanceToString($this->getInstance());
    }
}
