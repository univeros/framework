<?php
namespace Altair\tests\Structure\Pair;

trait __get
{
    public function testPropertyAccess()
    {
        $pair = $this->getPair('a', 1);

        $this->assertEquals('a', $pair->key);
        $this->assertEquals(1,  $pair->value);
    }

    public function testBadPropertyAccess()
    {
        $pair = $this->getPair('a', 1);
        $this->expectPropertyDoesNotExistException();
        $pair->nope;
    }
}
