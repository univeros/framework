<?php
namespace Altair\tests\Structure\Map;

trait reverse
{
    public function reversedDataProvider()
    {
        $reverse = function ($a) {
            return [$a[0], array_reverse($a[1], 1)];
        };

        return array_map($reverse, $this->basicDataProvider());
    }

    /**
     * @dataProvider reversedDataProvider
     */
    public function testReverse(array $values, array $expected)
    {
        $instance = $this->getInstance($values);
        $this->assertToArray($expected, $instance->reverse());
    }
}
