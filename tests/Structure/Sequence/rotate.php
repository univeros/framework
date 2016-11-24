<?php
namespace Altair\tests\Structure\Sequence;

trait rotate
{
    public function rotateDataProvider()
    {
        // values, rotation, expected
        return [
            [['a', 'b', 'c'],  0, ['a', 'b', 'c']],
            [['a', 'b', 'c'],  1, ['b', 'c', 'a']],
            [['a', 'b', 'c'],  2, ['c', 'a', 'b']],
            [['a', 'b', 'c'],  3, ['a', 'b', 'c']],
            [['a', 'b', 'c'],  4, ['b', 'c', 'a']],

            [['a', 'b', 'c'], -1, ['c', 'a', 'b']],
            [['a', 'b', 'c'], -2, ['b', 'c', 'a']],
            [['a', 'b', 'c'], -3, ['a', 'b', 'c']],
            [['a', 'b', 'c'], -4, ['c', 'a', 'b']],

            // Test short sequences too
            [['a'], -2, ['a']],
            [['a'], -1, ['a']],
            [['a'],  0, ['a']],
            [['a'],  1, ['a']],
            [['a'],  2, ['a']],

            [['a', 'b'], -3, ['b', 'a']],
            [['a', 'b'], -2, ['a', 'b']],
            [['a', 'b'], -1, ['b', 'a']],
            [['a', 'b'],  0, ['a', 'b']],
            [['a', 'b'],  1, ['b', 'a']],
            [['a', 'b'],  2, ['a', 'b']],
            [['a', 'b'],  3, ['b', 'a']],

            [[], -2, []],
            [[], -1, []],
            [[],  0, []],
            [[],  1, []],
            [[],  2, []],
        ];
    }

    /**
     * @dataProvider rotateDataProvider
     */
    public function testRotate(array $values, int $rotation, array $expected)
    {
        $instance = $this->getInstance($values);
        $instance->rotate($rotation);
        $this->assertToArray($expected, $instance);
    }
}
