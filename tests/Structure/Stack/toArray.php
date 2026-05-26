<?php
namespace Altair\Tests\Structure\Stack;

trait toArray
{
    /**
     * @dataProvider basicDataProvider
     */
    public function testToArray(array $values, array $expected)
    {
        $instance = static::getInstance($values);
        $this->assertToArray($expected, $instance);
    }
}
