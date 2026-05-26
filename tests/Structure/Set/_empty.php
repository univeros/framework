<?php
namespace Altair\Tests\Structure\Set;

trait _empty
{
    public function testArrayAccessEmpty(): void
    {
        $set = static::getInstance();
        $this->expectArrayAccessUnsupportedException();
        empty($set['a']);
    }
}
