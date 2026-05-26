<?php
namespace Altair\Tests\Structure\Sequence;

trait _empty
{
    public static function emptyDataProvider(): array
    {
        // initial, index, empty
        return [
            [[0], 0, true],

            [[null], 0, true],

            [['a'], 0, false],
            [['a'], 1, true],

            [['a', 'b'], 0, false],
            [['a', 'b'], 1, false],
            [['a', 'b'], 2, true],

            [[null, 'b'], 0, true],
            [[null, 'b'], 1, false],

            [['a', null], 1, true],
            [['a', null], 0, false],

            [['a', 'b', 'c'], 0, false],
            [['a', 'b', 'c'], 1, false],
            [['a', 'b', 'c'], 2, false],
            [['a', 'b', 'c'], 3, true],

            [[null, 'b', 'c'], 0, true],
            [['a', null, 'c'], 1, true],
            [['a', 'b', null], 2, true],
        ];
    }

    /**
     * @dataProvider emptyDataProvider
     */
    public function testArrayAccessEmpty(mixed $initial, mixed $index, bool $empty): void
    {
        $instance = static::getInstance($initial);
        $this->assertEquals($empty, empty($instance[$index]));
    }

    /**
     * @dataProvider badIndexDataProvider
     */
    public function testArrayAccessEmptyIndexBadIndex(mixed $initial, mixed $index): void
    {
        $instance = static::getInstance($initial);
        $this->assertTrue(empty($instance[$index]));
    }

    /**
     * @dataProvider outOfRangeDataProvider
     */
    public function testArrayAccessEmptyIndexOutOfRange(mixed $initial, mixed $index): void
    {
        $instance = static::getInstance($initial);
        $this->assertTrue(empty($instance[$index]));
    }

    /**
     * @dataProvider emptyDataProvider
     */
    public function testArrayAccessEmptyByReference(mixed $initial, mixed $index, bool $empty): void
    {
        $instance = static::getInstance([$initial]);
        $this->assertEquals($empty, empty($instance[0][$index]));
    }
}
