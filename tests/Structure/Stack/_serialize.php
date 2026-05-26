<?php
namespace Altair\Tests\Structure\Stack;


use PHPUnit\Framework\Attributes\DataProvider;
trait _serialize
{
    #[DataProvider('basicDataProvider')]
    public function testSerialize(array $values, array $expected): void
    {
        // Stack has to be serialized in push order, so that values can be
        // pushed when being unserialized.
        $expected = array_reverse($expected);

        $instance = static::getInstance($values);
        $this->assertSerialized($expected, $instance, false);
    }
}
