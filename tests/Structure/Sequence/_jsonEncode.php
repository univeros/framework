<?php
namespace Altair\Tests\Structure\Sequence;

trait _jsonEncode
{
    public function jsonEncodeDataProvider()
    {
        return $this->basicDataProvider();
    }

    /**
     * @dataProvider jsonEncodeDataProvider
     */
    public function testJsonEncode(array $initial, array $expected)
    {
        $instance = $this->getInstance($initial);
        $this->assertEquals(json_encode($expected), json_encode($instance));
    }
}
