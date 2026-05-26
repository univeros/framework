<?php
namespace Altair\Tests\Structure\Sequence;

trait _echo
{
    public function testEcho(): void
    {
        $this->assertInstanceToString(static::getInstance());
    }
}
