<?php
namespace Altair\Tests\Structure\Queue;

trait _jsonEncode
{
    /**
     * @dataProvider basicDataProvider
     */
    public function testJsonEncode(array $initial, array $expected)
    {
        $instance = static::getInstance($initial);
        $this->assertEquals(json_encode($expected), json_encode($instance));
    }
}
