<?php
namespace Altair\tests\Structure\Map;

trait _serialize
{
    /**
     * @dataProvider basicDataProvider
     */
    public function testSerialize(array $values, array $expected)
    {
        $instance = $this->getInstance($values);
        $this->assertSerialized($expected, $instance, true);
    }
}
