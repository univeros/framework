<?php
namespace Altair\Tests\Structure\Sequence;


use PHPUnit\Framework\Attributes\DataProvider;
trait _isset
{
    public static function issetDataProvider(): array
    {
        // initial, index, isset
        return [
            [[0], 0, true],

            [[null], 0, false],

            [['a'], 0, true],
            [['a'], 1, false],

            [['a', 'b'], 0, true],
            [['a', 'b'], 1, true],
            [['a', 'b'], 2, false],

            [[null, 'b'], 0, false],
            [['a', null], 1, false],

            [['a', 'b', 'c'], 0, true],
            [['a', 'b', 'c'], 1, true],
            [['a', 'b', 'c'], 2, true],
            [['a', 'b', 'c'], 3, false],

            [[null, 'b', 'c'], 0, false],
            [['a', null, 'c'], 1, false],
            [['a', 'b', null], 2, false],
        ];
    }

    #[DataProvider('issetDataProvider')]
    public function testArrayAccessIsset(mixed $initial, mixed $index, bool $isset): void
    {
        $instance = static::getInstance($initial);
        $this->assertEquals($isset, isset($instance[$index]));
    }

    #[DataProvider('badIndexDataProvider')]
    public function testArrayAccessIssetIndexBadIndex(mixed $initial, mixed $index): void
    {
        $instance = static::getInstance($initial);
        $this->assertFalse(isset($instance[$index]));
    }

    #[DataProvider('outOfRangeDataProvider')]
    public function testArrayAccessIssetIndexOutOfRange(mixed $initial, mixed $index): void
    {
        $instance = static::getInstance($initial);
        $this->assertFalse(isset($instance[$index]));
    }

    #[DataProvider('issetDataProvider')]
    public function testArrayAccessIssetByReference(mixed $initial, mixed $index, bool $isset): void
    {
        $instance = static::getInstance([$initial]);
        $this->assertEquals($isset, isset($instance[0][$index]));
    }
}
