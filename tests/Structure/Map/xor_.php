<?php
namespace Altair\Tests\Structure\Map;

trait xor_
{
    public function xorDataProvider()
    {
        // Keys in either A or B, but not both.
        // A, B, expected result
        return [
            [[],                    [],                   []],
            [['a' => 1],            ['a' => 2],           []],
            [['a' => 1],            ['b' => 2],           ['a' => 1, 'b' => 2]],
            [['a' => 1, 'b' => 2],  ['a' => 3],           ['b' => 2]],
            [['a' => 1, 'b' => 2],  ['b' => 4],           ['a' => 1]],
            [['a' => 1, 'b' => 2],  ['c' => 3, 'd' => 4], ['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4]],
        ];
    }

    /**
     * @dataProvider xorDataProvider
     */
    public function testXor(array $a, array $b, array $expected)
    {
        $a = $this->getInstance($a);
        $b = $this->getInstance($b);

        $this->assertEquals($expected, $a->xor($b)->toArray());
    }

    /**
     * @dataProvider xorDataProvider
     */
    public function testXorWithSelf(array $a, array $b, array $expected)
    {
        $map = $this->getInstance($a);

        $this->assertEquals([], $map->xor($map)->toArray());
    }
}
