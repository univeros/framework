<?php
namespace Altair\Tests\Structure\Sequence;

trait set
{
    public static function setDataProvider(): array
    {
        // initial, index, value, expected
        return [
            [['a'], 0, 'x', ['x']],

            [['a', 'b'], 0, 'x', ['x', 'b']],
            [['a', 'b'], 1, 'y', ['a', 'y']],

            [['a', 'b', 'c'], 0, 'x', ['x', 'b', 'c']],
            [['a', 'b', 'c'], 1, 'y', ['a', 'y', 'c']],
            [['a', 'b', 'c'], 2, 'z', ['a', 'b', 'z']],
        ];
    }

    /**
     * @dataProvider setDataProvider
     */
    public function testSet(mixed $initial, mixed $index, mixed $value, array $expected): void
    {
        $instance = static::getInstance($initial);

        $instance->set($index, $value);

        $this->assertToArray($expected, $instance);

        // set should not affect count
        $this->assertEquals(count($initial), count($instance));
    }

    /**
     * @dataProvider outOfRangeDataProvider
     */
    public function testSetOutOfRange(mixed $initial, mixed $index): void
    {
        $instance = static::getInstance($initial);
        $this->expectIndexOutOfRangeException();
        $instance->set($index, 1);
    }

    /**
     * @dataProvider badIndexDataProvider
     */
    public function testSetIndexBadIndex(mixed $initial, mixed $index): void
    {
        $instance = static::getInstance();
        $this->expectWrongIndexTypeException();
        $instance->set($index, 1);
    }

    /**
     * @dataProvider setDataProvider
     */
    public function testArrayAccessSet(mixed $initial, mixed $index, mixed $value, array $expected): void
    {
        $instance = static::getInstance($initial);
        $instance[$index] = $value;
        $this->assertToArray($expected, $instance);
        $this->assertEquals(count($expected), count($instance));
    }

    /**
     * @dataProvider badIndexDataProvider
     */
    public function testArrayAccessSetIndexBadIndex(mixed $initial, mixed $index): void
    {
        $instance = static::getInstance($initial);
        $this->expectWrongIndexTypeException();
        $instance[$index] = 1;
    }

    /**
     * @dataProvider outOfRangeDataProvider
     */
    public function testArrayAccessSetIndexOutOfRange(mixed $initial, mixed $index): void
    {
        $instance = static::getInstance($initial);
        $this->expectIndexOutOfRangeException();
        $instance[$index] = 1;
    }

    public function testArrayAccessSetByReference(): void
    {
        $instance = static::getInstance([[1]]);
        $instance[0][0] = 2;

        $this->assertToArray([[2]], $instance);
    }
}
