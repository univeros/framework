<?php
namespace Altair\Tests\Structure\Queue;

trait _list
{
    public function testList(): void
    {
        $instance = static::getInstance(['a', 'b', 'c']);
        $this->expectListNotSupportedException();
        [$a, $b, $c] = $instance;
    }
}
