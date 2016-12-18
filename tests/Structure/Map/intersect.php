<?php
namespace Altair\Tests\Structure\Map;

trait intersect
{
    public function intersectDataProvider()
    {
        // Keys in A that are also in B.
        // A, B, expected result
        return [
            [[],                    [],                   []],
            [['a' => 1],            ['b' => 2],           []],
            [['a' => 1],            ['a' => 2],           ['a' => 1]],
            [['a' => 1, 'b' => 2],  ['a' => 3, 'b' => 4], ['a' => 1, 'b' => 2]],
            [['b' => 2, 'a' => 1],  ['a' => 3, 'b' => 4], ['b' => 2, 'a' => 1]],
        ];
    }

    /**
     * @dataProvider intersectDataProvider
     */
    public function testIntersect(array $a, array $b, array $expected)
    {
        $a = $this->getInstance($a);
        $b = $this->getInstance($b);

        $this->assertEquals($expected, $a->intersect($b)->toArray());
    }

    /**
     * @dataProvider intersectDataProvider
     */
    public function testIntersectWithSelf(array $a, array $b, array $expected)
    {
        $map = $this->getInstance($a);

        $this->assertEquals($a, $map->intersect($map)->toArray());
    }
}
