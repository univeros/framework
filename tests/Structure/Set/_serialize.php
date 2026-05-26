<?php
namespace Altair\Tests\Structure\Set;

trait _serialize
{
    /**
     * @dataProvider basicDataProvider
     */
    public function testSerialize(array $values, array $expected): void
    {
        $instance = static::getInstance($values);
        $this->assertSerialized($expected, $instance, false);
    }
}
