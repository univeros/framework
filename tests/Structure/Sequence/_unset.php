<?php
namespace Altair\Tests\Structure\Sequence;


use PHPUnit\Framework\Attributes\DataProvider;
trait _unset
{
    #[DataProvider('removeDataProvider')]
    public function testArrayAccessUnset(mixed $initial, mixed $index, mixed $return, array $expected): void
    {
        $instance = static::getInstance($initial);
        unset($instance[$index]);
        $this->assertToArray($expected, $instance);
        $this->assertEquals(count($expected), count($instance));
    }

    #[DataProvider('badIndexDataProvider')]
    public function testArrayAccessUnsetIndexBadIndex(mixed $initial, mixed $index): void
    {
        $instance = static::getInstance($initial);
        $this->assertFalse(isset($instance[$index]));
        unset($instance[$index]);
    }

    #[DataProvider('outOfRangeDataProvider')]
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
