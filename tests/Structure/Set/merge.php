<?php
namespace Altair\Tests\Structure\Set;


use PHPUnit\Framework\Attributes\DataProvider;
trait merge
{
    public static function mergeDataProvider(): array
    {
        // A, B, expected
        return [
            [[],          [],       []],
            [[],          ['a'],    ['a']],
            [['a'],       ['a'],    ['a']],
            [['a'],       ['b'],    ['a', 'b']],
            [['a', 'b'],  [],       ['a', 'b']],
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
