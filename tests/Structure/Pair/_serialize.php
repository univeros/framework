<?php
namespace Altair\tests\Structure\Pair;

trait _serialize
{
    public function testSerialize()
    {
        $pair = $this->getPair('a', 1);
        $this->assertSerialized(['a', 1], $pair, false);
    }
}
