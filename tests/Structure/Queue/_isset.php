<?php
namespace Altair\Tests\Structure\Queue;

trait _isset
{
    public function testArrayAccessIsset(): void
    {
        $set = static::getInstance();
        $this->expectArrayAccessUnsupportedException();
        isset($set['a']);
    }
}
