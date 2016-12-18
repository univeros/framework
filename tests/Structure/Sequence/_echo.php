<?php
namespace Altair\Tests\Structure\Sequence;

trait _echo
{
    public function testEcho()
    {
        $this->assertInstanceToString($this->getInstance());
    }
}
