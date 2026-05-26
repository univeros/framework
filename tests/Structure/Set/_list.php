<?php
namespace Altair\Tests\Structure\Set;

trait _list
{
    public function testList(): void
    {
        $instance = static::getInstance(['a', 'b', 'c']);
        [$a, $b, $c] = $instance;

        $this->assertEquals('a', $a);
        $this->assertEquals('b', $b);
        $this->assertEquals('c', $c);
    }
}
