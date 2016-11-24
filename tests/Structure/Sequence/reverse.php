<?php
namespace Altair\tests\Structure\Sequence;

trait reverse
{
    public function reversedDataProvider()
    {
        return array_map(function ($a) {
            return [$a[0], array_reverse($a[1])];
        },
            $this->basicDataProvider()
        );
    }

    /**
     * @dataProvider reversedDataProvider
     */
    public function testReversed(array $values, array $expected)
    {
        $instance = $this->getInstance($values);
        $this->assertToArray($values, $instance);
        $instance = $instance->reverse();
        $this->assertToArray($expected, $instance);
    }
}
