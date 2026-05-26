<?php
namespace Altair\Tests\Structure\Map;

trait reverse
{
    public static function reversedDataProvider()
    {
        $reverse = function ($a) {
            return [$a[0], array_reverse($a[1], 1)];
        };

        return array_map($reverse, static::basicDataProvider());
    }

    /**
     * @dataProvider reversedDataProvider
     */
    public function testReverse(array $values, array $expected)
    {
        $instance = static::getInstance($values);
        $this->assertToArray($expected, $instance->reverse());
    }
}
