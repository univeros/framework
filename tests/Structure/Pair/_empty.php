<?php
namespace Altair\Tests\Structure\Pair;

trait _empty
{
    public function testPropertyEmptyKey(): void
    {
        $pair = $this->getPair('a', 1);
        $this->assertFalse(empty($pair->key));
        $this->assertTrue(empty($pair->nope));

        $pair = $this->getPair(false, 1);
        $this->assertTrue(empty($pair->key));
    }

    public function testPropertyEmptyValue(): void
    {
        $pair = $this->getPair('a', 1);
        $this->assertFalse(empty($pair->value));
        $this->assertTrue(empty($pair->nope));

        $pair = $this->getPair('a', false);
        $this->assertTrue(empty($pair->value));
    }
}
