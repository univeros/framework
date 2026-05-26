<?php
namespace Altair\Tests\Structure\Map;


use PHPUnit\Framework\Attributes\DataProvider;
trait intersect
{
    public static function intersectDataProvider(): array
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

    #[DataProvider('intersectDataProvider')]
    public function testIntersect(array $a, array $b, array $expected): void
    {
        $a = static::getInstance($a);
        $b = static::getInstance($b);

        $this->assertEquals($expected, $a->intersect($b)->toArray());
    }

    #[DataProvider('intersectDataProvider')]
    public function testIntersectWithSelf(array $a, array $b, array $expected): void
    {
        $map = static::getInstance($a);

        $this->assertEquals($a, $map->intersect($map)->toArray());
    }
}
