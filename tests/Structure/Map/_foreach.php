<?php
namespace Altair\Tests\Structure\Map;

trait _foreach
{
    /**
     * @dataProvider basicDataProvider
     */
    public function testForEach(array $values, array $expected): void
    {
        $instance = static::getInstance($values);
        $this->assertForEach($expected, $instance);
    }
}
