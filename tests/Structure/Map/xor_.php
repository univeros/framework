<?php
namespace Altair\Tests\Structure\Map;


use PHPUnit\Framework\Attributes\DataProvider;
trait xor_
{
    public static function xorDataProvider(): array
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

    #[DataProvider('xorDataProvider')]
    public function testXor(array $a, array $b, array $expected): void
    {
        $a = static::getInstance($a);
        $b = static::getInstance($b);

        $this->assertEquals($expected, $a->xor($b)->toArray());
    }

    #[DataProvider('xorDataProvider')]
    public function testXorWithSelf(array $a, array $b, array $expected): void
    {
        $map = static::getInstance($a);

        $this->assertEquals([], $map->xor($map)->toArray());
    }
}
