<?php
namespace Altair\Tests\Structure\Pair;

trait _serialize
{
    public function testSerialize(): void
    {
        $pair = $this->getPair('a', 1);
        $this->assertSerialized(['a', 1], $pair, false);
    }
}
