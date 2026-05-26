<?php
namespace Altair\Tests\Structure\Sequence;

trait remove
{
    public static function removeDataProvider(): array
    {
        // initial, index, return, expected
        return [
            [['a'], 0, 'a', []],

            [['a', 'b'], 0, 'a', ['b']],
            [['a', 'b'], 1, 'b', ['a']],

            [['a', 'b', 'c'], 0, 'a', ['b', 'c']],
            [['a', 'b', 'c'], 1, 'b', ['a', 'c']],
            [['a', 'b', 'c'], 2, 'c', ['a', 'b']],
        ];
    }

    /**
     * @dataProvider removeDataProvider
     */
    public function testRemove(mixed $initial, mixed $index, mixed $return, array $expected): void
    {
        $instance = static::getInstance($initial);
        $returned = $instance->remove($index);

        $this->assertEquals(count($initial) - 1, count($instance));
        $this->assertToArray($expected, $instance);
        $this->assertEquals($return, $returned);
    }

    /**
     * @dataProvider outOfRangeDataProvider
     */
    public function testRemoveIndexOutOfRange(mixed $initial, mixed $index): void
    {
        $instance = static::getInstance($initial);
        $this->expectIndexOutOfRangeException();
        $instance->remove($index);
    }

    /**
     * @dataProvider badIndexDataProvider
     */
    public function testRemoveIndexBadIndex(mixed $initial, mixed $index): void
    {
        $instance = static::getInstance($initial);
        $this->expectWrongIndexTypeException();
        $instance->remove($index);
    }
}
