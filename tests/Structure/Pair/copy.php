<?php
namespace Altair\Tests\Structure\Pair;

trait copy
{
    public function testCopy()
    {
        $pair = $this->getPair('a', 1);
        $copy = $pair->copy();

        $copy->key = 'x';
        $copy->value = 2;

        $this->assertEquals(['key' => 'a', 'value' => 1], $pair->toArray());
        $this->assertEquals(['key' => 'x', 'value' => 2], $copy->toArray());
    }
}
