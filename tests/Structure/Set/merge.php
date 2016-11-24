<?php
namespace Altair\tests\Structure\Set;

trait merge
{
    public function mergeDataProvider()
    {
        // A, B, expected
        return [
            [[],          [],       []],
            [[],          ['a'],    ['a']],
            [['a'],       ['a'],    ['a']],
            [['a'],       ['b'],    ['a', 'b']],
            [['a', 'b'],  [],       ['a', 'b']],
        ];
    }

    /**
     * @dataProvider mergeDataProvider
     */
    public function testMerge(array $initial, array $values, array $expected)
    {
        $instance = $this->getInstance($initial);

        $this->assertToArray($expected, $instance->merge($values));
        $this->assertToArray($initial, $instance);
    }

    /**
     * @dataProvider mergeDataProvider
     */
    public function testMergeWithSelf(array $initial, array $values, array $expected)
    {
        $instance = $this->getInstance($initial);

        $this->assertToArray($initial, $instance->merge($instance));
        $this->assertToArray($initial, $instance);
    }
}
