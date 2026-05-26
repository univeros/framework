<?php
namespace Altair\Tests\Structure\Sequence;

trait toArray
{
    public static function toArrayDataProvider()
    {
        return static::basicDataProvider();
    }

    /**
     * @dataProvider toArrayDataProvider
     */
    public function testToArray(array $values, array $expected): void
    {
        $instance = static::getInstance($values);
        $this->assertToArray($expected, $instance);
    }
}
