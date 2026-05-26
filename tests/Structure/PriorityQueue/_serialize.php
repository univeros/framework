<?php
namespace Altair\Tests\Structure\PriorityQueue;

trait _serialize
{
    public static function serializeDataProvider()
    {
        return [
            [
                ['a' => 1, 'b' => 2], ['b' => 2, 'a' => 1],
            ],
        ];
    }

    /**
     * @dataProvider serializeDataProvider
     */
    public function testSerialize(array $values, array $expected)
    {
        $instance = static::getInstance($values);
        $this->assertSerialized($expected, $instance, true);
    }
}
