<?php
namespace Altair\Tests\Structure\Set;

trait _echo
{
    public function testEcho()
    {
        $this->assertInstanceToString(static::getInstance());
    }
}
