<?php
namespace Altair\Tests\Structure\PriorityQueue;

trait toArray
{
    /**
     * @dataProvider basicDataProvider
     */
    public function testToArray(array $values, array $expected): void
    {
        $instance = static::getInstance($values);

        // Also check that toArray is not destructive
        $this->assertToArray($expected, $instance);
        $this->assertToArray($expected, $instance);
    }
}
