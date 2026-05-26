<?php
namespace Altair\Tests\Structure\Map;


use PHPUnit\Framework\Attributes\DataProvider;
trait merge
{
    public static function mergeDataProvider(): array
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

    #[DataProvider('mergeDataProvider')]
    public function testMerge(array $initial, array $values, array $expected): void
    {
        $instance = static::getInstance($initial);

        $this->assertToArray($expected, $instance->merge($values));
        $this->assertToArray($initial, $instance);
    }

    #[DataProvider('mergeDataProvider')]
    public function testMergeWithSelf(array $initial, array $values, array $expected): void
    {
        $instance = static::getInstance($initial);

        $this->assertToArray($initial, $instance->merge($instance));
        $this->assertToArray($initial, $instance);
    }
}
