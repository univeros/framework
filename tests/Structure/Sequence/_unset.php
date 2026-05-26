<?php
namespace Altair\Tests\Structure\Sequence;

trait _unset
{
    /**
     * @dataProvider removeDataProvider
     */
    public function testArrayAccessUnset(mixed $initial, mixed $index, mixed $return, array $expected): void
    {
        $instance = static::getInstance($initial);
        unset($instance[$index]);
        $this->assertToArray($expected, $instance);
        $this->assertEquals(count($expected), count($instance));
    }

    /**
     * @dataProvider badIndexDataProvider
     */
    public function testArrayAccessUnsetIndexBadIndex(mixed $initial, mixed $index): void
    {
        $instance = static::getInstance($initial);
        $this->assertFalse(isset($instance[$index]));
        unset($instance[$index]);
    }

    /**
     * @dataProvider outOfRangeDataProvider
     */
    public function testArrayAccessUnsetIndexOutOfRange(mixed $initial, mixed $index): void
    {
        $instance = static::getInstance($initial);
        $this->assertFalse(isset($instance[$index]));
        unset($instance[$index]);
    }

    public function testArrayAccessUnsetByReference(): void
    {
        $instance = static::getInstance([[1]]);
        unset($instance[0][0]);

        $this->assertToArray([[]], $instance);
    }
}
