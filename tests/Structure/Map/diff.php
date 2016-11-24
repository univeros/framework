<?php
namespace Altair\tests\Structure\Map;

trait diff
{
    public function diffDataProvider()
    {
        // Keys in A but not in B.
        // A, B, expected result
        return [
            [[],                    [],                   []],
            [['a' => 1],            ['a' => 2],           []],
            [['a' => 1],            ['b' => 2],           ['a' => 1]],
            [['a' => 1, 'b' => 2],  ['a' => 3],           ['b' => 2]],
            [['a' => 1, 'b' => 2],  ['b' => 4],           ['a' => 1]],
            [['a' => 1, 'b' => 2],  ['c' => 3, 'd' => 4], ['a' => 1, 'b' => 2]],
        ];
    }

    /**
     * @dataProvider diffDataProvider
     */
    public function testDiff(array $a, array $b, array $expected)
    {
        $a = $this->getInstance($a);
        $b = $this->getInstance($b);

        $this->assertEquals($expected, $a->diff($b)->toArray());
    }

    /**
     * @dataProvider diffDataProvider
     */
    public function testDiffWithSelf(array $a, array $b, array $expected)
    {
        $map = $this->getInstance($a);

        $this->assertEquals([], $map->diff($map)->toArray());
    }
}
