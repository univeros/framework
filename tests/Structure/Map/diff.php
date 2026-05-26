<?php
namespace Altair\Tests\Structure\Map;


use PHPUnit\Framework\Attributes\DataProvider;
trait diff
{
    public static function diffDataProvider(): array
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

    #[DataProvider('diffDataProvider')]
    public function testDiff(array $a, array $b, array $expected): void
    {
        $a = static::getInstance($a);
        $b = static::getInstance($b);

        $this->assertEquals($expected, $a->diff($b)->toArray());
    }

    #[DataProvider('diffDataProvider')]
    public function testDiffWithSelf(array $a, array $b, array $expected): void
    {
        $map = static::getInstance($a);

        $this->assertEquals([], $map->diff($map)->toArray());
    }
}
