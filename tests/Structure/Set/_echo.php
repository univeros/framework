<?php
namespace Altair\Tests\Structure\Set;

trait _echo
{
    public function testEcho(): void
    {
        $this->assertInstanceToString(static::getInstance());
    }
}
