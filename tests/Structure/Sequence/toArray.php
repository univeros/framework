<?php
namespace Altair\Tests\Structure\Sequence;


use PHPUnit\Framework\Attributes\DataProvider;
trait toArray
{
    public static function toArrayDataProvider()
    {
        return static::basicDataProvider();
    }

    #[DataProvider('toArrayDataProvider')]
    public function testToArray(array $values, array $expected): void
    {
        $instance = static::getInstance($values);
        $this->assertToArray($expected, $instance);
    }
}
