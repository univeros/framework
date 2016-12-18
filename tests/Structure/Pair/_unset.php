<?php
namespace Altair\Tests\Structure\Pair;

trait _unset
{
    public function testPropertyUnsetKey()
    {
        $pair = $this->getPair('a', 1);
        unset($pair->key);
        $this->assertNull($pair->key);
    }

    public function testPropertyUnsetValue()
    {
        $pair = $this->getPair('a', 1);
        unset($pair->value);
        $this->assertNull($pair->value);
    }
}
