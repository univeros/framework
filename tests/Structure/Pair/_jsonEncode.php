<?php
namespace Altair\tests\Structure\Pair;

trait _jsonEncode
{
    public function testJsonEncode()
    {
        $instance = $this->getPair('a', 1);
        $expected = json_encode([
            'key' => 'a',
            'value' => 1,
        ]);

        $this->assertEquals($expected, json_encode($instance));
    }
}
