<?php
namespace Altair\Tests\Structure\Sequence;


use PHPUnit\Framework\Attributes\DataProvider;
trait unshift
{
    public static function unshiftDataProvider()
    {
        return static::basicDataProvider();
    }

    #[DataProvider('unshiftDataProvider')]
    public function testUnshiftVariadic(array $initial, array $values): void
    {
        $instance = static::getInstance($initial);

        $instance->unshift(...$values);

        $expected = array_merge($values, $initial);

        $this->assertToArray($expected, $instance);
        $this->assertEquals(count($expected), count($instance));
    }

    #[DataProvider('unshiftDataProvider')]
    public function testUnshift(array $initial, array $values): void
    {
        $instance = static::getInstance($initial);

        foreach ($values as $value) {
            $instance->unshift($value);
        }

        $expected = array_merge(array_reverse($values), $initial);

        $this->assertToArray($expected, $instance);
        $this->assertEquals(count($expected), count($instance));
    }
}
