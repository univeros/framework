<?php
namespace Altair\Tests\Structure\Map;

trait _echo
{
    public function testEcho(): void
    {
        $this->assertInstanceToString(static::getInstance());
    }
}
