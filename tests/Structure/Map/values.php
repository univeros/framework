<?php
namespace Altair\Tests\Structure\Map;

use Altair\Structure\Vector;

trait values
{
    public static function valuesDataProvider(): array
    {
        return [
            [[], []],

            [['a' => 1, 'b' => 2], [1, 2]],

            [range(0, self::MANY), range(0, self::MANY)],
        ];
    }

    /**
     * @dataProvider valuesDataProvider
     */
    public function testValues(array $initial, array $expected): void
    {
        $instance = static::getInstance($initial);
        $values = $instance->values();

        $this->assertInstanceOf(Vector::class, $values);
        $this->assertEquals($expected, $values->toArray());
    }
}
