<?php
namespace Altair\Tests\Structure\Map;

trait merge
{
    public function mergeDataProvider()
    {
        // A, B, expected
        return [
            [[],                    [],                 []],
            [[],                    ['a' => 1],         ['a' => 1]],
            [['a' => 1],            ['a' => 2],         ['a' => 2]],
            [['a' => 1],            ['b' => 2],         ['a' => 1, 'b' => 2]],
            [['b' => 2],            ['a' => 1],         ['b' => 2, 'a' => 1]],
            [['a' => 1, 'b' => 2],  ['c' => 3],         ['a' => 1, 'b' => 2, 'c' => 3]],
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
