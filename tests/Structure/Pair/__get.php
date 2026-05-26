<?php
namespace Altair\Tests\Structure\Pair;

trait __get
{
    public function testPropertyAccess(): void
    {
        $pair = $this->getPair('a', 1);

        $this->assertEquals('a', $pair->key);
        $this->assertEquals(1, $pair->value);
    }

    public function testBadPropertyAccess(): void
    {
        $pair = $this->getPair('a', 1);
        $this->expectPropertyDoesNotExistException();
        $pair->nope;
    }
}
