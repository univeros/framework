<?php
namespace Altair\Tests\Structure\Pair;

trait _echo
{
    public function testEcho(): void
    {
        $this->assertInstanceToString($this->getPair('a', 1));
    }
}
