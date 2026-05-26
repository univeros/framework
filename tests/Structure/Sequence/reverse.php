<?php
namespace Altair\Tests\Structure\Sequence;

trait reverse
{
    public static function reversedDataProvider()
    {
        return array_map(
            function ($a) {
                return [$a[0], array_reverse($a[1])];
            },
            static::basicDataProvider()
        );
    }

    /**
     * @dataProvider reversedDataProvider
     */
    public function testReversed(array $values, array $expected)
    {
        $instance = static::getInstance($values);
        $this->assertToArray($values, $instance);
        $instance = $instance->reverse();
        $this->assertToArray($expected, $instance);
    }
}
