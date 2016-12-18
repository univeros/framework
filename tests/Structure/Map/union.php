<?php
namespace Altair\Tests\Structure\Map;

trait union
{
    public function unionDataProvider()
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
     * @dataProvider unionDataProvider
     */
    public function testUnion(array $initial, array $values, array $expected)
    {
        $instance = $this->getInstance($initial);
        $other = $this->getInstance($values);

        $this->assertToArray($expected, $instance->union($other));
        $this->assertToArray($initial, $instance);
    }

    /**
     * @dataProvider unionDataProvider
     */
    public function testUnionWithSelf(array $initial, array $values, array $expected)
    {
        $instance = $this->getInstance($initial);

        $this->assertToArray($initial, $instance->union($instance));
        $this->assertToArray($initial, $instance);
    }
}
