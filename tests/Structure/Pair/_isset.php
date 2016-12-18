<?php
namespace Altair\Tests\Structure\Pair;

trait _isset
{
    public function testPropertyIssetKey()
    {
        $pair = $this->getPair('a', 1);
        $this->assertTrue(isset($pair->key));
        $this->assertFalse(isset($pair->nope));

        $pair = $this->getPair(null, 1);
        $this->assertFalse(isset($pair->key));
    }

    public function testPropertyIssetValue()
    {
        $pair = $this->getPair('a', 1);
        $this->assertTrue(isset($pair->value));
        $this->assertFalse(isset($pair->nope));

        $pair = $this->getPair('a', null);
        $this->assertFalse(isset($pair->value));
    }

    public function testPropertyExists()
    {
        $pair = $this->getPair('a', 1);

        $this->assertTrue(property_exists($pair, 'key'));
        $this->assertTrue(property_exists($pair, 'value'));

        $this->assertFalse(property_exists($pair, 'foo'));
        $this->assertFalse(property_exists($pair, 'bar'));
    }
}
