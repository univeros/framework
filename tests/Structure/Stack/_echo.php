<?php
namespace Altair\Tests\Structure\Stack;

trait _echo
{
    public function testEcho(): void
    {
        $this->assertInstanceToString(static::getInstance());
    }
}
