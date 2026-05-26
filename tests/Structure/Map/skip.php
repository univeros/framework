<?php
namespace Altair\Tests\Structure\Map;


use PHPUnit\Framework\Attributes\DataProvider;
trait skip
{
    public static function skipDataProvider(): array
    {
        // values, position, returned pair
        return [
            [['a'],             0, [0, 'a']],
            [['a', 'b'],        0, [0, 'a']],
            [['a', 'b'],        1, [1, 'b']],
            [['a', 'b', 'c'],   0, [0, 'a']],
            [['a', 'b', 'c'],   1, [1, 'b']],
            [['a', 'b', 'c'],   2, [2, 'c']],
        ];
    }

    public static function skipOutOfRangeDataProvider(): array
    {
        return [
            [[], -1],
            [[],  0],
            [[],  1],
            [['a'], -1],
            [['a'],  1],
        ];
    }

    #[DataProvider('skipDataProvider')]
    public function testSkip(array $values, int $position, array $expected): void
    {
        $instance = static::getInstance($values);
        $pair = $instance->skip($position);

        $this->assertEquals($expected, [$pair->key, $pair->value]);
    }

    #[DataProvider('skipOutOfRangeDataProvider')]
    public function testSkipIndexOutOfRange(array $values, int $position): void
    {
        $this->expectIndexOutOfRangeException();
        $instance = static::getInstance($values);
        $instance->skip($position);
    }
}
