<?php
namespace Altair\Tests\Structure\Queue;

trait _echo
{
    public function testEcho(): void
    {
        $this->assertInstanceToString(static::getInstance());
    }
}
