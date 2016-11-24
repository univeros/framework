<?php
namespace Altair\tests\Structure\Pair;

trait _echo
{
    public function testEcho()
    {
        $this->assertInstanceToString($this->getPair('a', 1));
    }
}
